<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class User extends Model
{
    const SESSION = "User";

    const SECRET = "HcodePhp7_Secret";
    const SECRET_IV = "HcodePhp7_Secret";
    //define('SECRET_IV', pack('a16', 'senha'));
    //define('SECRET', pack('a16', 'senha'));

    public static function getFromSession()
    {
        $user = new User();

        if (isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0)
        {
            $user->setData($_SESSION[User::SESSION]);
        }

        return $user;
    }

    public static function checkLogin($inadmin = true)
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
            )
            {
                //Não está logado
                return false;
            } else {
                if ($inadmin ===true && $_SESSION[User::SESSION]['inadmin'] ===true)
                {
                    return true;
                } else if ($inadmin === false)
                {
                    return true;
                } else {
                    return false;
                }
            }
    }
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
        if (User::checkLogin($inadmin))
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
    public static function getForgot($email)
    {
        $sql = new Sql();

        $results = $sql->select("
            Select *
            from tb_persons a
            inner join tb_users b using(idperson)
            where a.desemail = :EMAIL", array(
                ":EMAIL"=>$email
            ));
        if (count($results) ===0)
        {
            throw new \Exception("Não foi possível recuperar a senha");
        } else {
            $data = $results[0];

            $results2 = $sql->select("call sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
                ":iduser" => $data["iduser"],
                ":desip"=> $_SERVER["REMOTE_ADDR"]
            ));

            if (count($results2) === 0 )
            {
                throw new \Exception("Não foi possível recuperar a senha");

            } else {
                $dataRecovery = $results2[0];

                $code = base64_encode(openssl_encrypt($dataRecovery["idrecovery"], 'AES-128-CBC', User::SECRET, 0, User::SECRET_IV));
                $link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";

                $mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir Senha do Hcode Store", "forgot", array(
                        "name" => $data["desperson"],
                        "link" => $link
                ));

                $mailer->send();

                return $data;
            }
        }
    }
    public static function validForgotDecrypt($code)
    {
        $idrecovery =openssl_decrypt(base64_decode($code), 'AES-128-CBC', User::SECRET, 0, User::SECRET_IV);

        $sql = new Sql();

        $results = $sql->select("select *
            from tb_userspasswordsrecoveries A
               inner join tb_users B using(iduser)
               inner join tb_persons C using(idperson)
            where
               A.idrecovery = :idrecovery and
               A.dtrecovery is null and
               date_add(A.dtregister, INTERVAL 1 HOUR) >=now()", array(
                   ":idrecovery"=>$idrecovery
               ));
        if (count($results) === 0)
        {
            throw new \Exception("Não foi possível recuperar a senha");

        } else {
            return $results[0];
        }
    }
    public static function setForgotUsed($idrecovery)
    {
        $sql = new Sql();

        $sql->query("update tb_userspasswordsrecoveries set
            dtrecovery = NOW() where idrecovery = :idrecovery", array(
                ":idrecovery"=>$idrecovery
            ));

    }

    public function setPassword($password)
    {
        $sql = new Sql();

        $sql->query("update tb_users set despassword = :password
                where iduser = :iduser", array(
                    ":password"=>$password,
                    ":iduser"=>$this->getiduser()
                ));
    }
}

 ?>
