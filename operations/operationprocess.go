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

package operations

import (
	"github.com/pufferpanel/pufferpanel/v2"
	"github.com/pufferpanel/pufferpanel/v2/operations/alterfile"
	"github.com/pufferpanel/pufferpanel/v2/operations/command"
	"github.com/pufferpanel/pufferpanel/v2/operations/console"
	"github.com/pufferpanel/pufferpanel/v2/operations/download"
	"github.com/pufferpanel/pufferpanel/v2/operations/fabricdl"
	"github.com/pufferpanel/pufferpanel/v2/operations/forgedl"
	"github.com/pufferpanel/pufferpanel/v2/operations/mkdir"
	"github.com/pufferpanel/pufferpanel/v2/operations/mojangdl"
	"github.com/pufferpanel/pufferpanel/v2/operations/move"
	"github.com/pufferpanel/pufferpanel/v2/operations/sleep"
	"github.com/pufferpanel/pufferpanel/v2/operations/spongeforgedl"
	"github.com/pufferpanel/pufferpanel/v2/operations/writefile"
	"github.com/spf13/cast"
)

var commandMapping map[string]pufferpanel.OperationFactory

func LoadOperations() {
	commandMapping = make(map[string]pufferpanel.OperationFactory)

	loadCoreModules()
}

func GenerateProcess(directions []interface{}, environment pufferpanel.Environment, dataMapping map[string]interface{}, env map[string]string) (OperationProcess, error) {
	dataMap := make(map[string]interface{})
	for k, v := range dataMapping {
		dataMap[k] = v
	}

	dataMap["rootDir"] = environment.GetRootDirectory()
	operationList := make([]pufferpanel.Operation, 0)
	for _, mapping := range directions {

		var typeMap pufferpanel.MetadataType
		err := pufferpanel.UnmarshalTo(mapping, &typeMap)
		if err != nil {
			return OperationProcess{}, err
		}

		factory := commandMapping[typeMap.Type]
		if factory == nil {
			return OperationProcess{}, pufferpanel.ErrMissingFactory
		}

		mapCopy := make(map[string]interface{}, 0)

		//replace tokens
		for k, v := range typeMap.Metadata {
			switch r := v.(type) {
			case string:
				{
					mapCopy[k] = pufferpanel.ReplaceTokens(r, dataMap)
				}
			case []string:
				{
					mapCopy[k] = pufferpanel.ReplaceTokensInArr(r, dataMap)
				}
			case map[string]string:
				{
					mapCopy[k] = pufferpanel.ReplaceTokensInMap(r, dataMap)
				}
			case []interface{}:
				{
					//if we can convert this to a string list, we can work with it
					temp := cast.ToStringSlice(r)
					if len(temp) == len(r) {
						mapCopy[k] = pufferpanel.ReplaceTokensInArr(temp, dataMap)
					} else {
						mapCopy[k] = v
					}
				}
			default:
				mapCopy[k] = v
			}
		}

		envMap := pufferpanel.ReplaceTokensInMap(env, dataMap)

		opCreate := pufferpanel.CreateOperation{
			OperationArgs:        mapCopy,
			EnvironmentVariables: envMap,
			DataMap:              dataMap,
		}

		op := factory.Create(opCreate)

		operationList = append(operationList, op)
	}
	return OperationProcess{processInstructions: operationList}, nil
}

type OperationProcess struct {
	processInstructions []pufferpanel.Operation
}

func (p *OperationProcess) Run(env pufferpanel.Environment) (err error) {
	for p.HasNext() {
		err = p.RunNext(env)
		if err != nil {
			break
		}
	}
	return
}

func (p *OperationProcess) RunNext(env pufferpanel.Environment) error {
	var op pufferpanel.Operation
	op, p.processInstructions = p.processInstructions[0], p.processInstructions[1:]
	err := op.Run(env)
	return err
}

func (p *OperationProcess) HasNext() bool {
	return len(p.processInstructions) != 0 && p.processInstructions[0] != nil
}

func loadCoreModules() {
	commandFactory := command.Factory
	commandMapping[commandFactory.Key()] = commandFactory

	downloadFactory := download.Factory
	commandMapping[downloadFactory.Key()] = downloadFactory

	mkdirFactory := mkdir.Factory
	commandMapping[mkdirFactory.Key()] = mkdirFactory

	moveFactory := move.Factory
	commandMapping[moveFactory.Key()] = moveFactory

	alterFileFactory := alterfile.Factory
	commandMapping[alterFileFactory.Key()] = alterFileFactory

	writeFileFactory := writefile.Factory
	commandMapping[writeFileFactory.Key()] = writeFileFactory

	mojangFactory := mojangdl.Factory
	commandMapping[mojangFactory.Key()] = mojangFactory

	spongeforgeDlFactory := spongeforgedl.Factory
	commandMapping[spongeforgeDlFactory.Key()] = spongeforgeDlFactory

	forgeDlFactory := forgedl.Factory
	commandMapping[forgeDlFactory.Key()] = forgeDlFactory

	fabricDlFactory := fabricdl.Factory
	commandMapping[fabricDlFactory.Key()] = fabricDlFactory

	sleepFactory := sleep.Factory
	commandMapping[sleepFactory.Key()] = sleepFactory

	consoleFactory := console.Factory
	commandMapping[consoleFactory.Key()] = consoleFactory
}
