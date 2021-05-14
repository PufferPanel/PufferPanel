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

package console

import (
	"github.com/pufferpanel/pufferpanel/v2"
)

type Console struct {
	Text string
}

func (d Console) Run(env pufferpanel.Environment) error {
	env.DisplayToConsole(true, "Message: %s \n", d.Text)
	return nil
}
