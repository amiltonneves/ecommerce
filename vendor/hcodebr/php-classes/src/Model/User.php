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
    const ERROR = "UserError";
    const ERROR_REGISTER = "UserErrorRegister";
    const SUCCESS = "UserSuccess";

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
                if ($inadmin ===true && (bool)$_SESSION[User::SESSION]['inadmin'] ===true)
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

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b ON a.idperson = b.idperson WHERE a.deslogin = :LOGIN", array(
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

            $data['desperson'] = utf8_encode($data['desperson']);

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
        if (!User::checkLogin($inadmin))
        {
            if ($inadmin)
            {
                header("Location: /admin/login");
            } else {
                header("Location: /login");
            }
            //redirecionando para a tela de login

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
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>User::getPasswordHash($this->getdespassword()),
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

        $data = $results[0];

        $data['desperson'] = utf8_encode($data['desperson']);

        $this->setData($results[0]);

    }
    public function update()
    {
        $sql = new Sql();

        $results = $sql->select("call sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)",
        array(
            ":iduser"=>$this->getiduser(),
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>User::getPasswordHash($this->getdespassword()),
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
    public static function getForgot($email, $inadmin = true)
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
                
                if ($inadmin === true) 
                {
                    $link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";
                } else {
                    $link = "http://www.hcodecommerce.com.br/forgot/reset?code=$code";
                }              

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
    public static function setError($msg)
    {
        $_SESSION[User::ERROR] = $msg;

    }

    public static function getError()
    {
        $msg = (isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR]) ?  $_SESSION[User::ERROR] : "";

        User::clearError();

        return $msg;
    }

    public static function clearError()
    {
        $_SESSION[User::ERROR] = NULL;
    }

    public static function getPasswordHash($password)
    {
		return password_hash($password, PASSWORD_DEFAULT, [
			'cost'=>12
		]);
    }

    public static function setErrorRegister($msg)
    {
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public static function getErrorRegister()
    {
        $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';

        User::clearErrorRegister();

        return $msg;
    }

    public static function clearErrorRegister()
    {
        $_SESSION[User::ERROR_REGISTER] = null;
    }

    public static function checkLoginExist($login)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin", [
            ':deslogin'=>$login
        ]);

        return (count($results) > 0);
    }

        public static function setSuccess($msg)
        {
            $_SESSION[User::SUCCESS] = $msg;

        }

        public static function getSuccess()
        {
            $msg = (isset($_SESSION[User::SUCCESS]) && $_SESSION[User::SUCCESS]) ?  $_SESSION[User::SUCCESS] : "";

            User::clearSuccess();

            return $msg;
        }

        public static function clearSuccess()
        {
            $_SESSION[User::SUCCESS] = NULL;
        }

    public function getOrders()
    {
        $sql = new Sql();

        $results = $sql->select("
            SELECT * 
            FROM tb_orders a 
            INNER JOIN tb_ordersstatus b USING(idstatus)
            INNER JOIN tb_carts c USING(idcart)
            INNER JOIN tb_users d ON d.iduser = a.iduser
            INNER JOIN tb_addresses e USING(idaddress)
            INNER JOIN tb_persons f ON f.idperson = d.idperson
            WHERE a.iduser = :iduser", [
                ":iduser"=>$this->getiduser()
        ]);

        return $results;
    }
    public static function getPage($page = 1, $itemsPerPage = 10)
    {
        $start = ($page-1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("
            SELECT SQL_CALC_FOUND_ROWS * 
            FROM tb_users A 
            INNER JOIN tb_persons B USING(idperson) 
            ORDER BY B.desperson
            LIMIT $start, $itemsPerPage");

        $resultsTotal = $sql->select("SELECT FOUND_ROWS() as nrtotal;");

        return [
            'data'=>$results,
            'total'=>(int)$resultsTotal[0]["nrtotal"],
            'pages'=>ceil($resultsTotal[0]["nrtotal"] / $itemsPerPage)
        ];
    }
    public static function getPageSearch($search, $page = 1, $itemsPerPage = 10)
    {
        $start = ($page-1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("
            SELECT SQL_CALC_FOUND_ROWS * 
            FROM tb_users A 
            INNER JOIN tb_persons B USING(idperson) 
            WHERE 
                B.desperson LIKE :search OR
                B.desemail = :search OR
                A.deslogin LIKE :search
            ORDER BY B.desperson
            LIMIT $start, $itemsPerPage", [
                ':search'=>"%".$search."%"
            ]);


        $resultsTotal = $sql->select("SELECT FOUND_ROWS() as nrtotal;");

        return [
            'data'=>$results,
            'total'=>(int)$resultsTotal[0]["nrtotal"],
            'pages'=>ceil($resultsTotal[0]["nrtotal"] / $itemsPerPage)
        ];
    }
}

 ?>
