/*
 Copyright 2016 Padduck, LLC

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 	http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
*/

package docker

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/docker/docker/api/types"
	"github.com/docker/docker/api/types/container"
	"github.com/docker/docker/api/types/filters"
	"github.com/docker/docker/api/types/network"
	"github.com/docker/docker/api/types/strslice"
	"github.com/docker/docker/client"
	"github.com/docker/go-connections/nat"
	"github.com/pufferpanel/pufferpanel/v2"
	"github.com/pufferpanel/pufferpanel/v2/logging"
	"github.com/pufferpanel/pufferpanel/v2/messages"
	"io"
	"io/ioutil"
	"os"
	"runtime"
	"syscall"
	"time"
)

type docker struct {
	*pufferpanel.BaseEnvironment
	ContainerId  string              `json:"-"`
	ImageName    string              `json:"image"`
	Binds        map[string]string   `json:"bindings,omitempty"`
	NetworkMode  string              `json:"networkMode,omitempty"`
	Network      string              `json:"networkName,omitempty"`
	Ports        []string            `json:"portBindings,omitempty"`
	Resources    container.Resources `json:"resources,omitempty"`
	ExposedPorts nat.PortSet         `json:"exposedPorts,omitempty"`

	connection       types.HijackedResponse
	cli              *client.Client
	downloadingImage bool
}

func (d *docker) dockerExecuteAsync(steps pufferpanel.ExecutionData) error {
	running, err := d.IsRunning()
	if err != nil {
		return err
	}
	if running {
		return pufferpanel.ErrContainerRunning
	}

	d.Wait.Wait()

	if d.downloadingImage {
		return pufferpanel.ErrImageDownloading
	}

	dockerClient, err := d.getClient()
	ctx := context.Background()

	//TODO: This logic may not work anymore, it's complicated to use an existing container with install/uninstall
	exists, err := d.doesContainerExist(dockerClient, ctx)

	if err != nil {
		return err
	}

	if exists {
		return errors.New("docker container already exists")
	}

	d.ImageName = pufferpanel.ReplaceTokens(d.ImageName, steps.Data)

	err = d.createContainer(dockerClient, ctx, steps.Command, steps.Arguments, steps.Environment, steps.WorkingDirectory)
	if err != nil {
		return err
	}

	config := types.ContainerAttachOptions{
		Stdin:  true,
		Stdout: true,
		Stderr: true,
		Stream: true,
	}

	d.connection, err = dockerClient.ContainerAttach(ctx, d.ContainerId, config)
	if err != nil {
		return err
	}

	d.Wait.Add(1)

	go func() {
		defer d.connection.Close()
		wrapper := d.CreateWrapper()
		_, _ = io.Copy(wrapper, d.connection.Reader)
		c, _ := d.getClient()
		err = c.ContainerStop(context.Background(), d.ContainerId, nil)
		d.Wait.Done()
		if err != nil {
			logging.Error().Printf("Error stopping container "+d.ContainerId, err)
		}

		msg := messages.Status{Running: false}
		_ = d.WSManager.WriteMessage(msg)

		if steps.Callback != nil {
			steps.Callback(err == nil)
		}
	}()

	startOpts := types.ContainerStartOptions{}

	msg := messages.Status{Running: true}
	_ = d.WSManager.WriteMessage(msg)

	err = dockerClient.ContainerStart(ctx, d.ContainerId, startOpts)
	if err != nil {
		return err
	}
	return err
}

func (d *docker) ExecuteInMainProcess(cmd string) (err error) {
	running, err := d.IsRunning()
	if err != nil {
		return
	}
	if !running {
		err = pufferpanel.ErrServerOffline
		return
	}

	_, _ = d.connection.Conn.Write([]byte(cmd + "\n"))
	return
}

func (d *docker) Kill() (err error) {
	running, err := d.IsRunning()
	if err != nil {
		return err
	}

	if !running {
		return
	}

	dockerClient, err := d.getClient()
	if err != nil {
		return err
	}
	err = dockerClient.ContainerKill(context.Background(), d.ContainerId, "SIGKILL")
	return
}

func (d *docker) Create() error {
	return os.Mkdir(d.RootDirectory, 0755)
}

func (d *docker) IsRunning() (bool, error) {
	dockerClient, err := d.getClient()
	if err != nil {
		return false, err
	}

	ctx := context.Background()

	exists, err := d.doesContainerExist(dockerClient, ctx)
	if !exists {
		return false, err
	}

	stats, err := dockerClient.ContainerInspect(ctx, d.ContainerId)
	if err != nil {
		return false, err
	}
	return stats.State.Running, nil
}

func (d *docker) GetStats() (*pufferpanel.ServerStats, error) {
	running, err := d.IsRunning()
	if err != nil {
		return nil, err
	}

	if !running {
		return nil, pufferpanel.ErrServerOffline
	}

	dockerClient, err := d.getClient()

	if err != nil {
		return nil, err
	}

	ctx := context.Background()
	res, err := dockerClient.ContainerStats(ctx, d.ContainerId, false)
	defer func() {
		if res.Body != nil {
			pufferpanel.Close(res.Body)
		}
	}()
	if err != nil {
		return nil, err
	}

	data := &types.StatsJSON{}
	err = json.NewDecoder(res.Body).Decode(&data)
	if err != nil {
		return nil, err
	}

	return &pufferpanel.ServerStats{
		Memory: calculateMemoryPercent(data),
		Cpu:    calculateCPUPercent(data),
	}, nil
}

func (d *docker) WaitForMainProcess() error {
	return d.WaitForMainProcessFor(0)
}

func (d *docker) WaitForMainProcessFor(timeout int) (err error) {
	running, err := d.IsRunning()
	if err != nil {
		return
	}
	if running {
		if timeout > 0 {
			var timer = time.AfterFunc(time.Duration(timeout)*time.Millisecond, func() {
				err = d.Kill()
			})
			d.Wait.Wait()
			timer.Stop()
		} else {
			d.Wait.Wait()
		}
	}
	return
}

func (d *docker) getClient() (*client.Client, error) {
	var err error = nil
	if d.cli == nil {
		d.cli, err = client.NewClientWithOpts(client.FromEnv)
		ctx := context.Background()
		d.cli.NegotiateAPIVersion(ctx)
	}
	return d.cli, err
}

func (d *docker) doesContainerExist(client *client.Client, ctx context.Context) (bool, error) {
	opts := types.ContainerListOptions{
		Filters: filters.NewArgs(),
	}

	opts.All = true
	opts.Filters.Add("name", d.ContainerId)

	existingContainers, err := client.ContainerList(ctx, opts)

	if len(existingContainers) == 0 {
		return false, err
	} else {
		return true, err
	}
}

func (d *docker) pullImage(client *client.Client, ctx context.Context, force bool) error {
	exists := false

	opts := types.ImageListOptions{
		All:     true,
		Filters: filters.NewArgs(),
	}
	opts.Filters.Add("reference", d.ImageName)
	images, err := client.ImageList(ctx, opts)

	if err != nil {
		return err
	}

	if len(images) >= 1 {
		exists = true
	}

	logging.Debug().Printf("Does image %v exist? %v", d.ImageName, exists)

	if exists && !force {
		return nil
	}

	op := types.ImagePullOptions{}

	logging.Debug().Printf("Downloading image %v", d.ImageName)
	d.DisplayToConsole(true, "Downloading image for container, please wait\n")

	d.downloadingImage = true

	r, err := client.ImagePull(ctx, d.ImageName, op)
	defer pufferpanel.Close(r)
	if err != nil {
		return err
	}
	_, err = io.Copy(ioutil.Discard, r)

	d.downloadingImage = false
	logging.Debug().Printf("Downloaded image %v", d.ImageName)
	d.DisplayToConsole(true, "Downloaded image for container\n")
	return err
}

func (d *docker) createContainer(client *client.Client, ctx context.Context, cmd string, args []string, env map[string]string, root string) error {
	logging.Debug().Printf("Creating container")
	containerRoot := "/pufferpanel"
	err := d.pullImage(client, ctx, false)

	if err != nil {
		return err
	}

	cmdSlice := strslice.StrSlice{}

	cmdSlice = append(cmdSlice, cmd)

	for _, v := range args {
		cmdSlice = append(cmdSlice, v)
	}

	//newEnv := os.Environ()
	newEnv := []string{"HOME=" + containerRoot}

	for k, v := range env {
		newEnv = append(newEnv, fmt.Sprintf("%s=%s", k, v))
	}

	config := &container.Config{
		AttachStderr:    true,
		AttachStdin:     true,
		AttachStdout:    true,
		Tty:             true,
		OpenStdin:       true,
		NetworkDisabled: false,
		Cmd:             cmdSlice,
		Image:           d.ImageName,
		WorkingDir:      containerRoot,
		Env:             newEnv,
		ExposedPorts:    d.ExposedPorts,
	}

	if runtime.GOOS == "linux" {
		config.User = fmt.Sprintf("%d:%d", os.Getuid(), os.Getgid())
	}

	hostConfig := &container.HostConfig{
		AutoRemove:   true,
		NetworkMode:  container.NetworkMode(d.NetworkMode),
		Resources:    d.Resources,
		Binds:        []string{root + ":" + containerRoot},
		PortBindings: nat.PortMap{},
	}

	for k, v := range d.Binds {
		hostConfig.Binds = append(hostConfig.Binds, k+":"+v)
	}

	networkConfig := &network.NetworkingConfig{}

	_, bindings, err := nat.ParsePortSpecs(d.Ports)
	if err != nil {
		return err
	}
	hostConfig.PortBindings = bindings

	_, err = client.ContainerCreate(ctx, config, hostConfig, networkConfig, d.ContainerId)
	return err
}

func (d *docker) SendCode(code int) error {
	running, err := d.IsRunning()

	if err != nil || !running {
		return err
	}

	dockerClient, err := d.getClient()

	if err != nil {
		return err
	}

	ctx := context.Background()
	return dockerClient.ContainerKill(ctx, d.ContainerId, syscall.Signal(code).String())
}

func calculateCPUPercent(v *types.StatsJSON) float64 {
	// Max number of 100ns intervals between the previous time read and now
	possIntervals := uint64(v.Read.Sub(v.PreRead).Nanoseconds()) // Start with number of ns intervals
	possIntervals /= 100                                         // Convert to number of 100ns intervals
	//possIntervals *= uint64(v.NumProcs)                          // Multiple by the number of processors

	// Intervals used
	intervalsUsed := v.CPUStats.CPUUsage.TotalUsage - v.PreCPUStats.CPUUsage.TotalUsage

	// Percentage avoiding divide-by-zero
	if possIntervals > 0 {
		return float64(intervalsUsed) / float64(possIntervals)
	}
	return 0.00
}

func calculateMemoryPercent(v *types.StatsJSON) float64 {
	return float64(v.MemoryStats.Usage)
}
