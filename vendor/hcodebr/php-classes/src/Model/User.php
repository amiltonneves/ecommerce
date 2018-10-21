<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;

class User extends Model
{
    const SESSION = "User";

    public static function login($login, $password)
    {
        $sql = new Sql();

        $results = $sql->select("select * from tb_users  where deslogin= :LOGIN", array(
            ":LOGIN"=>$login
        ));

        if (count($results) === 0)
        {
            throw new \Exception("Usuário inexistente ou senha inválida");
        }
        $data = $results[0];

        if (password_verify($password, $data["despassword"]) === true)
        {
            $user = new User();

            $user->setData($data);

            // Criando sessão e trazendo os valores
            $_SESSION[User::SESSION] = $user->getValues();

            return $user;

        } else {
            throw new \Exception("Usuário inexistente ou senha inválida");
        }
    }
    public static function verifyLogin($inadmin = true)
    {
        if (
            // verificando se a sessão não foi definida
            !isset($_SESSION[User::SESSION])
            || // ou
            // definida mas não contem valor (vazia ou perdeu o valor)
            !$_SESSION[User::SESSION]
            ||
            // verificando se o usuário não existir
            !(int)$_SESSION[User::SESSION]["iduser"] > 0
            ||
            // verificando se o usuário não é administrador
            (bool)$_SESSION[User::SESSION]["inadmin"] !==$inadmin
            )
            {
                //redirecionando para a tela de login
                header("Location: /admin/login");
                exit;
            }
        }
    public static function logout()
    {
        $_SESSION[User::SESSION] = NULL;
    }
}

 ?>
