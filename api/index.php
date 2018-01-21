<?php

require 'vendor/autoload.php';

$app = new Slim\App();

$app->add(new \Slim\Middleware\HttpBasicAuthentication(array(
  //Add authentication for POST and DELETE actions
  "path" => [
    "/post",
    "/delete",
    "/revert"
  ],
  "secure" => true,
  "relaxed" => [
    "localhost"
  ],
  //All users
  "users" => [
    "demouser" => "123",
  ],
  "error" => function ($request, $response, $arguments) {
    return $response->withStatus(401)->withJson(["error" => "Unauthorized! Please check your credentials!"]);
  }
)));

//GET the whole JSON file
$app->get('/get', function ($request, $response, $args) {
  $data = file_get_contents('data.json');
  $json = json_decode($data, true);

  return $response->withJson($json);
});

//GET data by id
$app->get('/get/{id}', function ($request, $response, $args) {
  $data = file_get_contents('data.json');
  $json = json_decode($data, true);

  $id = $request->getAttribute('id');

  if ($json[0][$id]) {
    return $response->withJson($json[0][$id]);
  } else {
    return $response->withStatus(404)->withJson(["error" => "Could not find data with specified id!"]);
  }
});

//POST a new answer
$app->post('/post/{id}', function ($request, $response, $args) {
  $data = file_get_contents('data.json');
  $file = json_decode($data, true);

  $id = $request->getAttribute('id');
  $body = $request->getParsedBody();

  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't update data! No request body specified!"]);
  }
  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }

  //Set new name if value exists in the body of the request
  if (isset($body['name'])) {
    $file[0][$id]['name'] = $body['name'];
  }
  //Set new type if value exists in the body of the request
  if (isset($body['type'])) {
    $file[0][$id]['type'] = $body['type'];
  }
  //Loop through values to set answer and their amounts
  if (isset($body['values'])) {
    foreach ($body['values'] as $language => $name) {
      $amount = 0;
      if (isset($file[0][$id]['values'][$language][$name])) {
        $amount = $file[0][$id]['values'][$language][$name];
      }
      $file[0][$id]['values'][$language][$name] = $amount + 1;
    }
  }

  $newFile = json_encode($file, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  file_put_contents('data.json', $newFile);

  return $response->withJson($file);
});

//DELETE a specific dataset
$app->delete('/delete/{id}', function ($request, $response, $args) {
  $data = file_get_contents('data.json');
  $json = json_decode($data, true);

  $id = $request->getAttribute('id');

  if ($json[0][$id]) {
    unset($json[0][$id]);
    $newFile = json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    file_put_contents('data.json', $newFile);

    return $response->withJson($json);
  } else {
    return $response->withStatus(404)->withJson(["error" => "Could not find data with specified id!"]);
  }
});

//undo an answer by decreasing the amount
$app->post('/revert/{id}', function ($request, $response, $args) {
  $data = file_get_contents('data.json');
  $file = json_decode($data, true);

  $id = $request->getAttribute('id');
  $body = $request->getParsedBody();

  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't update data! No request body specified!"]);
  }
  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }

  //Loop through values to decrease answer by 1
  if (isset($body['values'])) {
    foreach ($body['values'] as $language => $name) {
      $amount = 0;
      if (isset($file[0][$id]['values'][$language][$name])) {
        $amount = $file[0][$id]['values'][$language][$name];
      }
      if ($amount != 0 && $amount != 1) {
        $file[0][$id]['values'][$language][$name] = $amount - 1;
      } else {
        unset($file[0][$id]['values']);
      }
    }
  } else {
    return $response->withStatus(400)->withJson(["error" => "No values found in request body!"]);
  }

  $newFile = json_encode($file, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  file_put_contents('data.json', $newFile);

  return $response->withJson($file);
});

$app->run();

?>
