/*
 Copyright 2019 Padduck, LLC
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

package pufferpanel

type Server struct {
	Type
	Variables      map[string]Variable `json:"data,omitempty"`
	Display        string              `json:"display,omitempty"`
	Environment    interface{}         `json:"environment,omitempty"`
	Installation   []interface{}       `json:"install,omitempty"`
	Uninstallation []interface{}       `json:"uninstall,omitempty"`
	Identifier     string              `json:"id,omitempty"`
	Execution      Execution           `json:"run,omitempty"`
	Tasks          []Task              `json:"tasks,omitempty"`
}

type Task struct {
	Cron       string        `json:"cron,omitempty"`
	Operations []interface{} `json:"operations,omitempty"`
}

type Variable struct {
	Type
	Description  string           `json:"desc,omitempty"`
	Display      string           `json:"display,omitempty"`
	Internal     bool             `json:"internal,omitempty"`
	Required     bool             `json:"required,omitempty"`
	Value        interface{}      `json:"value,omitempty"`
	UserEditable bool             `json:"userEdit,omitempty"`
	Options      []VariableOption `json:"options,omitempty"`
}

type VariableOption struct {
	Value   interface{} `json:"value"`
	Display string      `json:"display"`
}

type Execution struct {
	Command                 string            `json:"command,omitempty"`
	StopCommand             string            `json:"stop,omitempty"`
	Disabled                bool              `json:"disabled,omitempty"`
	AutoStart               bool              `json:"autostart,omitempty"`
	AutoRestartFromCrash    bool              `json:"autorecover,omitempty"`
	AutoRestartFromGraceful bool              `json:"autorestart,omitempty"`
	PreExecution            []interface{}     `json:"pre,omitempty"`
	PostExecution           []interface{}     `json:"post,omitempty"`
	StopCode                int               `json:"stopCode,omitempty"`
	EnvironmentVariables    map[string]string `json:"environmentVars,omitempty"`
	LegacyRun               string            `json:"program,omitempty"`
	LegacyArguments         []string          `json:"arguments,omitempty"`
	WorkingDirectory        string            `json:"workingDirectory,omitempty"`
}

type Template struct {
	Server
	SupportedEnvironments []interface{} `json:"supportedEnvironments,omitempty"`
}

type Type struct {
	Type string `json:"type"`
}
