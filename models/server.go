/*
 Copyright 2020 Padduck, LLC
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

package models

import (
	"github.com/pufferpanel/pufferpanel/v2"
	"gopkg.in/go-playground/validator.v9"
	"time"
)

type Server struct {
	Name       string `gorm:"size:40;NOT NULL" json:"-" validate:"required,printascii"`
	Identifier string `gorm:"UNIQUE_INDEX;NOT NULL;PRIMARY_KEY;size:8" json:"-" validate:"required,printascii"`

	NodeID uint `gorm:"NOT NULL" json:"-" validate:"required,min=1"`
	Node   Node `gorm:"ASSOCIATION_SAVE_REFERENCE:false" json:"-" validate:"-"`

	IP   string `gorm:"" json:"-" validate:"omitempty,ip|fqdn"`
	Port uint16 `gorm:"" json:"-" validate:"omitempty"`

	Type string `gorm:"NOT NULL;default='generic'" json:"-" validate:"required,printascii"`

	CreatedAt time.Time `json:"-"`
	UpdatedAt time.Time `json:"-"`
}

type Servers []*Server

func (s *Server) IsValid() (err error) {
	err = validator.New().Struct(s)
	if err != nil {
		err = pufferpanel.GenerateValidationMessage(err)
	}
	return
}

func (s *Server) BeforeSave() (err error) {
	err = s.IsValid()
	return
}
