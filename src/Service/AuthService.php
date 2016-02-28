<?php

namespace Service;

use Manager\UserManager;
use Model\User;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;

/**
 * @author Juan Luis Martínez <juanlu@comakai.com>
 */
class AuthService
{

    /**
     * @var UserManager
     */
    protected $um;

    /**
     * @var PasswordEncoderInterface
     */
    protected $encoder;

    /**
     * @var string
     */
    protected $secret;

    public function __construct(UserManager $um, PasswordEncoderInterface $encoder, $secret)
    {
        $this->um = $um;
        $this->encoder = $encoder;
        $this->secret = $secret;
    }

    /**
     * @param $username
     * @param $password
     * @return string
     */
    public function login($username, $password)
    {

        try {
            $user = $this->um->findUserBy(array('usernameCanonical' => $this->um->canonicalize($username)));
        } catch (\Exception $e) {
            throw new UnauthorizedHttpException('', 'El nombre de usuario y la contraseña que ingresaste no coinciden con nuestros registros.');
        }

        $encodedPassword = $user->getPassword();
        $salt = $user->getSalt();
        $valid = $this->encoder->isPasswordValid($encodedPassword, $password, $salt);

        if (!$valid) {
            throw new UnauthorizedHttpException('', 'El nombre de usuario y la contraseña que ingresaste no coinciden con nuestros registros.');
        }

        return $this->buildToken($user);
    }

    /**
     * @param string $id
     * @return string
     */
    public function getToken($id)
    {
        $user = $this->um->getById($id);

        return $this->buildToken($user);
    }

    /**
     * @param User $user
     * @return string
     */
    protected function buildToken(User $user)
    {
        $token = array(
            'iss' => 'https://nekuno.com',
            'exp' => time() + 5184000, // 60 days
            'sub' => $user->getUsernameCanonical(),
            'user' => $user->jsonSerialize(),
        );

        $jwt = \JWT::encode($token, $this->secret);

        return $jwt;
    }
}