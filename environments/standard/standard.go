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

package standard

import (
	"fmt"
	"github.com/pufferpanel/pufferpanel/v2"
	"github.com/pufferpanel/pufferpanel/v2/logging"
	"github.com/pufferpanel/pufferpanel/v2/messages"
	"github.com/shirou/gopsutil/process"
	"github.com/spf13/cast"
	"io"
	"os"
	"os/exec"
	"runtime"
	"strings"
	"syscall"
	"time"
)

type standard struct {
	*pufferpanel.BaseEnvironment
	mainProcess *exec.Cmd
	stdInWriter io.Writer
}

func (s *standard) standardExecuteAsync(steps pufferpanel.ExecutionData) (err error) {
	running, err := s.IsRunning()
	if err != nil {
		return
	}
	if running {
		err = pufferpanel.ErrProcessRunning
		return
	}
	s.Wait.Wait()
	s.Wait.Add(1)
	s.mainProcess = exec.Command(steps.Command, steps.Arguments...)
	s.mainProcess.Dir = steps.WorkingDirectory
	for _, v := range os.Environ() {
		if !strings.HasPrefix(v, "PUFFER_") {
			s.mainProcess.Env = append(s.mainProcess.Env, v)
		}
	}
	s.mainProcess.Env = append(s.mainProcess.Env, "HOME="+s.RootDirectory, "TERM=xterm-256color")
	for k, v := range steps.Environment {
		s.mainProcess.Env = append(s.mainProcess.Env, fmt.Sprintf("%s=%s", k, v))
	}
	wrapper := s.CreateWrapper()
	s.mainProcess.Stdout = wrapper
	s.mainProcess.Stderr = wrapper
	pipe, err := s.mainProcess.StdinPipe()
	if err != nil {
		return
	}
	s.stdInWriter = pipe
	logging.Info().Printf("Starting process: %s %s", s.mainProcess.Path, strings.Join(s.mainProcess.Args[1:], " "))

	msg := messages.Status{Running: true}
	_ = s.WSManager.WriteMessage(msg)

	err = s.mainProcess.Start()
	if err != nil && err.Error() != "exit status 1" {
		msg := messages.Status{Running: false}
		_ = s.WSManager.WriteMessage(msg)
		logging.Info().Printf("Process failed to start: %s", err)
		return
	} else {
		logging.Info().Printf("Process started (%d)", s.mainProcess.Process.Pid)
	}

	go s.handleClose(steps.Callback)
	return
}

func (s *standard) ExecuteInMainProcess(cmd string) (err error) {
	running, err := s.IsRunning()
	if err != nil {
		return err
	}
	if !running {
		err = pufferpanel.ErrServerOffline
		return
	}
	stdIn := s.stdInWriter
	_, err = io.WriteString(stdIn, cmd+"\n")
	return
}

func (s *standard) Kill() (err error) {
	running, err := s.IsRunning()
	if err != nil {
		return err
	}
	if !running {
		return
	}
	return s.mainProcess.Process.Kill()
}

func (s *standard) IsRunning() (isRunning bool, err error) {
	isRunning = s.mainProcess != nil && s.mainProcess.Process != nil
	if isRunning {
		pr, pErr := os.FindProcess(s.mainProcess.Process.Pid)
		if pr == nil || pErr != nil {
			isRunning = false
		} else if runtime.GOOS != "windows" && pr.Signal(syscall.Signal(0)) != nil {
			isRunning = false
		}
	}
	return
}

func (s *standard) GetStats() (*pufferpanel.ServerStats, error) {
	running, err := s.IsRunning()
	if err != nil {
		return nil, err
	}
	if !running {
		return nil, pufferpanel.ErrServerOffline
	}
	pr, err := process.NewProcess(int32(s.mainProcess.Process.Pid))
	if err != nil {
		return nil, err
	}

	memMap, _ := pr.MemoryInfo()
	cpu, _ := pr.Percent(time.Second * 1)

	return &pufferpanel.ServerStats{
		Cpu:    cpu,
		Memory: cast.ToFloat64(memMap.RSS),
	}, nil
}

func (s *standard) Create() error {
	return os.Mkdir(s.RootDirectory, 0755)
}

func (s *standard) WaitForMainProcess() error {
	return s.WaitForMainProcessFor(0)
}

func (s *standard) WaitForMainProcessFor(timeout int) (err error) {
	running, err := s.IsRunning()
	if err != nil {
		return
	}
	if running {
		if timeout > 0 {
			var timer = time.AfterFunc(time.Duration(timeout)*time.Millisecond, func() {
				err = s.Kill()
			})
			s.Wait.Wait()
			timer.Stop()
		} else {
			s.Wait.Wait()
		}
	}
	return
}

func (s *standard) SendCode(code int) error {
	running, err := s.IsRunning()

	if err != nil || !running {
		return err
	}

	return s.mainProcess.Process.Signal(syscall.Signal(code))
}

func (s *standard) handleClose(callback func(graceful bool)) {
	err := s.mainProcess.Wait()
	s.Wait.Done()

	msg := messages.Status{Running: false}
	_ = s.WSManager.WriteMessage(msg)

	var graceful bool
	if s.mainProcess == nil || s.mainProcess.ProcessState == nil || err != nil {
		graceful = false
	} else {
		graceful = s.mainProcess.ProcessState.Success()
	}

	if s.mainProcess != nil && s.mainProcess.Process != nil {
		_ = s.mainProcess.Process.Release()
	}

	s.mainProcess = nil
	s.stdInWriter = nil

	if callback != nil {
		callback(graceful)
	}
}
