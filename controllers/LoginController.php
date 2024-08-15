<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController {

    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            // echo 'Desde POST';

            $auth = new Usuario($_POST);
            
            
            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                // echo 'El usaurio proporciono correo y contraseña';
                //Comprobar que el usuario existe
               $usuario = Usuario::buscarPorCampo('email', $auth->email);

                //    debuguear($usuario);

                if($usuario) {
                    //Verificar la contraseña

                    if($usuario->comprobarContrasenaAndVerificado($auth->password)) {
                        //Autenticar usuario 
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . ' ' . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        //Redireccionamiento
                        if ($usuario->admin == 1 ) {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cliente');
                        }
                        
                        // debuguear($_SESSION);
                    }


                }else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
            }


            $usuario = new Usuario($_POST);


           
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login',[
            'alertas' => $alertas
        ]);
    }
    
    public static function logout() {
        echo 'Desde logout';
    }

    public static function olvide(Router $router) {
        
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            if(empty($alertas)) {
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                
                if($usuario && $usuario->confirmado == 1) {
                    //Generar token 
                    $usuario->crearToken();
                    $usuario->guardar();
                    //TODO: Enviar el email
                    $email = new Email(
                        $usuario->email,
                        $usuario->nombre,
                        $usuario->token
                    );

                    $email->enviarInstrucciones();
                    
                    Usuario::setAlerta('exito', 'Revisa tu email');
                    
                }else {
                    Usuario::setAlerta('error', 'El usuario no existe o no esta confirmado');
                    
                }
            }
        }
        $alertas = Usuario::getAlertas();
        
        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);

    }

    public static function recuperar(Router $router) {
        
        $alertas = [];

        $error = false;

        $token = s($_GET['token']);

        //Buscar usuario por su token 
        $usuario = Usuario::buscarPorCampo('token', $token);

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no valido');
            $error = true;
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            //leer el nuevo password y guardarlo 
            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();

            if(empty($alertas)) {
                $usuario->password = null;

                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado) {
                    header('Location: /');
                }   
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/recuperar-password', [
            'alertas' => $alertas,
            'error' => $error
        ]);

    }

    public static function crear(Router $router) {

        $alertas = [];

        $usuario = new Usuario($_POST);

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            
        $usuario->sincronizar($_POST); 
        $alertas = $usuario->validarNuevaCuenta();    

        //Revisar que alertas esten vacio 
        if(empty($alertas)) {

            //Verificar que el usuario no este registrado

            $resultado = $usuario->existeUsuario();

            if ($resultado->num_rows) {
                $alertas = Usuario::getAlertas();
            } else {

                //hashear el password
                $usuario->hashPassword();

                //Generar un token unico
                $usuario->crearToken();
                
                //Enviar el email
                $email = new Email($usuario->email, $usuario->nombre,$usuario->token);

                $email->enviarConfirmacion();

                //Crear el usuario
                $resultado = $usuario->guardar();



                //debuguear($usuario);

                if($resultado) {
                    header('location: /mensaje');
                }




            }

        }

       


        

           
        }

        $router->render('auth/crear-cuenta',[
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }

    public static function confirmar(Router $router) {
        $alertas = [];

        $token = s($_GET['token']);

        // debuguear($token);

        $usuario = Usuario::buscarPorCampo('token', $token);

        if(empty($usuario)) {
            // echo 'Token no valido';
            Usuario::setAlerta('error', 'Token no valido');
        } else {
            //Modificar a usuario confirmado
            // echo 'Token valido, confirmando usuario...';
            // debuguear($usuario);

            $usuario->confirmado = 1;
            $usuario->token = '';

            // debuguear($usuario);

            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada correctamente');

        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/confirmar-cuenta',[
            'alertas'=> $alertas
        ]);
    }

    public static function mensaje(Router $router) {
        
        $router->render('auth/mensaje');

    }

    public static function admin() {
        echo 'Desde admin';
    }

    public static function cliente() {
        echo 'Desde cliente';
    }


}