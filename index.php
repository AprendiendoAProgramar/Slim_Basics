<?php
// Start PHP session
session_start();

require 'vendor/autoload.php';
date_default_timezone_set('Europe/Madrid');

// use Monolog\Logger;
// use Monolog\Handler\StreamHandler;
//
// $log = new Logger('name');
// $log->pushHandler(new StreamHandler('app.txt', Logger::WARNING));
// $log->addWarning('Foo');

// Create Slim app
$app = new \Slim\App(['settings' => ['displayErrorDetails' => true]]);;

// Fetch DI Container
$container = $app->getContainer();

// Register Twig View helper
$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('templates', [
        //'cache' => 'cache'
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));

    return $view;
};

$container['flash'] = function () {
    return new \Slim\Flash\Messages();
};

// Add middleware
$app->add(function ($request, $response, $next) {
    $this->view->offsetSet('flash', $this->flash);
    return $next($request, $response);
});

$app->get('/', function ($request, $response, $args) {
    $response = $this->view->render($response, 'about.twig');
    return $response;
})->setName('home');

$app->get('/contact', function ($request, $response, $args) {
    $response = $this->view->render($response, 'contact.twig');
    return $response;
})->setName('contact');

$app->post('/contact', function ($request, $response, $args){
  $body = $this->request->getParsedBody();
// I chose to only call the getParsedBody() once,
// but you could also go $name = $this->request->getParsedBody()["name"]
  $name = $body["name"];
  $email = $body["email"];
  $msg = $body["msg"];
  $uri = $request->getUri();
  if(!empty($name) && !empty($email) && !empty($msg)){
    $cleanName = filter_var($name, FILTER_SANITIZE_STRING);
    $cleanEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
    $cleanMsg = filter_var($msg, FILTER_SANITIZE_STRING);
  } else {
  //  message the user that there was a problem
    $this->flash->addMessage("fail", "Empty");
    print_r($msg);
 //  Note: 'Location' refers to the current URI and 'contact' refers to my contact URL that I set with
 // ->setName('contact') at the end of my get() method for the Contact page. ->setName() is the new syntax
 //  for ->name()

    return $this->response->withStatus(200)->withHeader('Location', $uri);
  }

  // Create the Transport
  $transport = Swift_SmtpTransport::newInstance('smtp.bladis.com', 25)
    ->setUsername('miguel@bladis.com')
    ->setPassword('miguelbladis225')
    ;
  /*
  You could alternatively use a different transport such as Sendmail or Mail:
  // Sendmail
  $transport = Swift_SendmailTransport::newInstance('/usr/sbin/sendmail -bs');
  // Mail
  $transport = Swift_MailTransport::newInstance();
  */

  // Create the Mailer using your created Transport
  $mailer = \Swift_Mailer::newInstance($transport);

  // Create a message
  $message = \Swift_Message::newInstance('Wonderful Subject')
    ->setFrom(array($cleanEmail => $cleanName))
    ->setTo(array('miguel@bladis.com'))
    ->setBody($cleanMsg)
    ;

  // Send the message
  $result = $mailer->send($message);

  if ($result > 0) {
      // send a message that says thank you!!
      $this->flash->addMessage('success', 'Message ok');

      return $this->response->withStatus(200)->withHeader('Location', 'contact');
  } else {
      // send a message to the user that the message fail to send

      return $this->response->withStatus(200)->withHeader('Location', $uri);
      // log that there was an error
      $this->flash->addMessage('fail', 'Error');
  }

});

$app->run();
