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
    /* verifica o login */
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

    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select("select * from tb_users A inner join tb_persons B using(idperson) order by B.desperson");

    }
    public function save()
    {
        $sql = new Sql();

        $results = $sql->select("call sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)",
        array(
            ":desperson"=>$this->getdesperson(),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>$this->getdespassword(),
            ":desemail"=>$this->getdesemail(),
            ":nrphone"=>$this->getnrphone(),
            ":inadmin"=>$this->getinadmin()
        ));
        $this->setData($results[0]);
    }

    public function get($iduser)
    {
        $sql = new Sql();

        $results = $sql->select("select * from tb_users A inner join tb_persons B using(idperson) where A.iduser = :iduser", array(
            ":iduser"=>$iduser
        ));
        $this->setData($results[0]);

    }
    public function Update()
    {
        $sql = new Sql();

        $results = $sql->select("call sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)",
        array(
            ":iduser"=>$this->getiduser(),
            ":desperson"=>$this->getdesperson(),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>$this->getdespassword(),
            ":desemail"=>$this->getdesemail(),
            ":nrphone"=>$this->getnrphone(),
            ":inadmin"=>$this->getinadmin()
        ));
        $this->setData($results[0]);
    }
    public function delete()
    {
        $sql = new Sql();

        $sql->query("call sp_users_delete(:iduser)", array(
            ":iduser"=>$this->getiduser()
        ));
        
    }
}

 ?>
