<?php

require 'vendor/autoload.php';

$app = new Slim\App();

$app->add(new \Slim\Middleware\HttpBasicAuthentication(array(
  //Add authentication for POST and DELETE actions
  "path" => [
    "/answer",
    "/delete",
    "/revert",
    "/create"
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
  return $response->withJson(getFile());
});

//GET data by id
$app->get('/get/{id}', function ($request, $response, $args) {
  $json = getFile();
  $id = $request->getAttribute('id');

  if ($json[0][$id]) {
    return $response->withJson($json[0][$id]);
  } else {
    return $response->withStatus(404)->withJson(["error" => "Could not find data with specified id!"]);
  }
});

//POST a new answer
$app->post('/answer/{id}', function ($request, $response, $args) {
  $file = getFile();
  $id = $request->getAttribute('id');
  $body = $request->getParsedBody();

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't update data! No request body specified!"]);
  }
  if (!isset($body['values'])) {
    return $response->withStatus(400)->withJson(["error" => "Request body contains wrong or malformed data!"]);
  }

  //Loop through values to set answer and their amounts
  foreach ($body['values'] as $language => $name) {
    $amount = 0;
    if (isset($file[0][$id]['values'][$language][$name])) {
      $amount = getAmount($file, $id, $language, $name);
    }
    $file[0][$id]['values'][$language][$name] = $amount + 1;
  }

  saveFile($file);
  return $response->withJson($file);
});

//DELETE a specific dataset
$app->delete('/delete/{id}', function ($request, $response, $args) {
  $json = getFile();
  $id = $request->getAttribute('id');

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }

  if ($json[0][$id]) {
    unset($json[0][$id]);
    saveFile($json);
    return $response->withJson($json);
  } else {
    return $response->withStatus(404)->withJson(["error" => "Could not find data with specified id!"]);
  }
});

//undo an answer by decreasing the amount
$app->post('/revert/{id}', function ($request, $response, $args) {
  $file = getFile();
  $id = $request->getAttribute('id');
  $body = $request->getParsedBody();

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't update data! No request body specified!"]);
  }
  if (!isset($body['values'])) {
    return $response->withStatus(400)->withJson(["error" => "Request body contains wrong or malformed data!"]);
  }

  //Loop through values to decrease answer by 1
  foreach ($body['values'] as $language => $name) {
    if (isset($file[0][$id]['values'][$language][$name])) {
      $amount = getAmount($file, $id, $language, $name);

      //Delete the child "values" from the file if the amount is smaller than or equal to 1
      if ($amount > 1) {
        $file[0][$id]['values'][$language][$name] = $amount - 1;
      } else {
        unset($file[0][$id]['values']);
      }

      saveFile($file);
    } else {
      return $response->withStatus(404)->withJson(["error" => "No data found for this language and value!"]);
    }
  }

  return $response->withJson($file);
});

//Create a new dataset
$app->post('/create/{id}', function ($request, $response, $args) {
  $file = getFile();
  $id = $request->getAttribute('id');
  $body = $request->getParsedBody();

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for new dataset specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't create new dataset! No request body specified!"]);
  }
  if (!isset($body['name']) || !isset($body['type'])) {
    return $response->withStatus(400)->withJson(["error" => "Request body contains wrong or malformed data!"]);
  }
  if (isset($file[0][$id])) {
    return $response->withStatus(400)->withJson(["error" => "A dataset with this id does already exist!"]);
  }

  //Set the name of the dataset
  $file[0][$id]['name'] = $body['name'];
  //Set the new type
  $file[0][$id]['type'] = $body['type'];

  saveFile($file);

  return $response->withJson($file);
});

//Update an existing dataset
$app->post('/update/{id}', function ($request, $response, $args) {
  $file = getFile();
  $id = $request->getAttribute('id');
  $body = $request->getParsedBody();

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for new dataset specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't create new dataset! No request body specified!"]);
  }
  if (!isset($file[0][$id])) {
    return $response->withStatus(400)->withJson(["error" => "A dataset with this id does not exist!"]);
  }

  if (isset($body['name'])) {
    //Set the name of the dataset
    $file[0][$id]['name'] = $body['name'];
  }
  if (isset($body['type'])) {
    //Set the new type
    $file[0][$id]['type'] = $body['type'];
  }

  saveFile($file);

  return $response->withJson($file);
});

function saveFile($file)
{
 file_put_contents('data.json', json_encode($file, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function getFile()
{
  return json_decode(file_get_contents('data.json'), true);
}

function getAmount($file, $id, $language, $name)
{
  return $file[0][$id]['values'][$language][$name];
}

$app->run();

?>
