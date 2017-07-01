<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2016 Joe Nilson             <joenilson at gmail.com>
 * Copyright (C) 2017 Carlos García Gómez    <neorazorx at gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core\Model;

/**
 * Define un paquete de permisos para asignar rápidamente a usuarios.
 *
 * @author Joe Nilson            <joenilson at gmail.com>
 * @author Carlos García Gómez   <neorazorx at gmail.com>
 */
class Rol {

    use \FacturaScripts\Core\Base\Model;

    public $codrol;
    public $descripcion;

    public function __construct($data = FALSE) {
        $this->init(__CLASS__, 'fs_roles', 'codrol');
        if ($data) {
            $this->codrol = $data['codrol'];
            $this->descripcion = $data['descripcion'];
        } else {
            $this->clear();
        }
    }

    public function clear() {
        $this->codrol = NULL;
        $this->descripcion = NULL;
    }

    public function url() {
        if (is_null($this->codrol)) {
            return 'index.php?page=AdminRol';
        }

        return 'index.php?page=AdminRol&codrol=' . $this->codrol;
    }
    
    public function test() {
        $this->descripcion = $this->noHtml($this->descripcion);
        return TRUE;
    }

}