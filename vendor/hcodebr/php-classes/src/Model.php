<?php

namespace Hcode;

/**
 * Criando metodos geters e setters dinamicamente
 */
class Model
{
    private $values = []; // armazena todos os valores dos campos do objeto

    // metodo mágico que é chamado toda vez que um método for chamado
    public function __call($name, $args)
    {
        // pega os 3 primeiros caracteres do método (set ou get)
        $method = substr($name, 0, 3);

        // pega os demais caracteres do método (nome do campo)
        $fieldname = substr($name, 3, strlen($name));

        switch ($method)
        {
            case "get":
                return $this->values[$fieldname];
                break;

            case "set":
                $this->values[$fieldname] = $args[0];;
                break;
        }
    }
    public function setData($data = array())
    {
        foreach ($data as $key => $value) {
            $this->{"set".$key}($value);
        }
    }
    public function getValues()
    {
        return $this->values;
    }
}



 ?>
