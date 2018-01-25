<?php

require 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

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
  return $response->withJson(getFile($request->getHeaderLine('Accept-Language')));
});

//GET the whole data file as YAML
$app->get('/get/yaml', function ($request, $response, $args) {
  return $response->withHeader('Content-Type', 'text/yaml')->write(Yaml::dump(getFile($request->getHeaderLine('Accept-Language')), 2, 4, Yaml::DUMP_OBJECT_AS_MAP));
});

//GET data by id
$app->get('/get/{id}', function ($request, $response, $args) {
  $json = getFile($request->getHeaderLine('Accept-Language'));
  $id = $request->getAttribute('id');

  if ($json->$id) {
    return $response->withJson($json->$id);
  } else {
    return $response->withStatus(404)->withJson(["error" => "Could not find data with specified id!"]);
  }
});

//GET questions which are newer than the specified date
$app->get('/get/time/{time}', function ($request, $response, $args) {
  $json = getFile($request->getHeaderLine('Accept-Language'));
  $time = $request->getAttribute('time');
  return $response->withJson(filterTime($json, $time));
});

//GET questions which are newer than the specified date as YAML
$app->get('/get/yaml/time/{time}', function ($request, $response, $args) {
  $json = getFile($request->getHeaderLine('Accept-Language'));
  $time = $request->getAttribute('time');
  return $response->withHeader('Content-Type', 'text/yaml')->write(Yaml::dump(filterTime($json, $time), 2, 4, Yaml::DUMP_OBJECT_AS_MAP));
});

//POST a new answer
$app->post('/answer/{id}', function ($request, $response, $args) {
  $file = getFile();
  $id = $request->getAttribute('id');
  $requestBody = $request->getBody();
  $body = json_decode($requestBody);

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't update data! No request body specified!"]);
  }
  if (!isset($body->values)) {
    return $response->withStatus(400)->withJson(["error" => "Request body contains wrong or malformed data!"]);
  }

  //Loop through values to set answer and their amounts
  if (getTypeOfDataset($file, $id) != "image") {
    foreach ($body->values as $language => $name) {
      $amount = 0;
      if (isset($file->$id->values->$language->$name)) {
        $amount = getAmount($file, $id, $language, $name);
      }
      $file->$id->values->$language->$name = $amount + 1;
    }
  } else {
    foreach ($body->values as $language => $urls) {
      if ($file->$id->values->$language) {
        foreach ($urls as $singleUrl) {
          if (!isset($file->$id->values->$language) && !$file->$id->values->$language->$singleUrl) {
            array_push($file->$id->values->$language, $singleUrl);
          }
        }
      } else {
        $file->$id->values->$language = $urls;
      }
    }
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

  if ($json->$id) {
    unset($json->$id);
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
  $requestBody = $request->getBody();
  $body = json_decode($requestBody);

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for data specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't update data! No request body specified!"]);
  }
  if (!isset($body->values)) {
    return $response->withStatus(400)->withJson(["error" => "Request body contains wrong or malformed data!"]);
  }

  //Loop through values to decrease answer by 1
  if (getTypeOfDataset($file, $id) != "image") {
    foreach ($body->values as $language => $name) {
      if (isset($file->$id->values->$language->$name)) {
        $amount = getAmount($file, $id, $language, $name);

        //Delete the child "values" from the file if the amount is smaller than or equal to 1
        if ($amount > 1) {
          $file->$id->values->$language->$name = $amount - 1;
        } else {
          unset($file->$id->values);
        }

        saveFile($file);
      } else {
        return $response->withStatus(404)->withJson(["error" => "No data found for this language and value!"]);
      }
    }
  } else {
    foreach ($body->values as $language => $urls) {
      foreach ($urls as $singleUrl) {
        if (($key = array_search($singleUrl, $file->$id->values->$language)) !== false) {
          unset($file->$id->values->$language->$key);
          saveFile($file);
        } else {
          return $response->withStatus(404)->withJson(["error" => "No data found for this language and value!"]);
        }
      }
    }
  }

  return $response->withJson($file);
});

//Create a new dataset
$app->post('/create/{id}', function ($request, $response, $args) {
  $file = getFile();
  $titles = getTitles();

  $id = $request->getAttribute('id');
  $requestBody = $request->getBody();
  $body = json_decode($requestBody);

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for new dataset specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't create new dataset! No request body specified!"]);
  }
  if (!isset($body->name) || !isset($body->type) || !isset($body->icon)) {
    return $response->withStatus(400)->withJson(["error" => "Request body contains wrong or malformed data!"]);
  }
  if (isset($file->$id)) {
    return $response->withStatus(400)->withJson(["error" => "A dataset with this id does already exist!"]);
  }

  //Set the name of the dataset in the normal file and the title file
  $file->$id->name = $body->name;
  $titles->$id->name = $body->name;

  //Set the icon of the quest
  $file->$id->icon = $body->icon;

  //Set the publication date
  $file->$id->time = time();

  if (isPossibleType($body->type)) {
    //Set the new type
    $file->$id->type = $body->type;
  } else {
    return $response->withStatus(400)->withJson(["error" => "The specified type is not allowed!"]);
  }

  saveFile($file);
  saveTitles($titles);
  return $response->withJson($file);
});

//Update an existing dataset
$app->post('/update/{id}', function ($request, $response, $args) {
  $file = getFile();
  $titles = getTitles();

  $id = $request->getAttribute('id');
  $requestBody = $request->getBody();
  $body = json_decode($requestBody);

  if (!$id) {
    return $response->withStatus(400)->withJson(["error" => "No id for new dataset specified!"]);
  }
  if (!$body) {
    return $response->withStatus(400)->withJson(["error" => "Can't create new dataset! No request body specified!"]);
  }
  if (!isset($file->$id)) {
    return $response->withStatus(400)->withJson(["error" => "A dataset with this id does not exist!"]);
  }

  if (isset($body->name)) {
    //Set the name of the dataset
    $file->$id->name = $body->name;
    $titles->$id->name = $body->name;
  }
  if (isset($body->icon)) {
    //Set the icon of the question
    $file->$id->icon = $body->icon;
  }
  if (isset($body->type)) {
    if (isPossibleType($body->type)) {
      //Set the new type
      $file->$id->type = $body->type;
    } else {
      return $response->withStatus(400)->withJson(["error" => "The specified type is not allowed!"]);
    }
  }

  saveFile($file);
  saveTitles($titles);
  return $response->withJson($file);
});

function saveFile($file)
{
 file_put_contents('data.json', json_encode($file, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function saveTitles($file, $language='en')
{
  file_put_contents('titles/' + $language + '.json', json_encode($file, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
}
function getFile($language='en')
{
  $json = json_decode(file_get_contents('data.json'));
  $titles = getTitles($language, true);
  foreach ($titles as $key => $value) {
    $json->$key->name = $value;
  }
  return $json;
}
function getTitles($language='en', $array=false)
{
  if (file_exists('titles/' . $language . '.json')) {
    return json_decode(file_get_contents('titles/' . $language . '.json'), $array);
  }
  return json_decode(file_get_contents('titles/en.json'), $array);
}

function getAmount($file, $id, $language, $name)
{
  return $file->$id->values->$language->$name;
}
function getTypeOfDataset($file, $id)
{
  return $file->$id->type;
}
function isPossibleType($type)
{
  if ($type == "YesNo") {
    return true;
  } else if ($type == "translation") {
    return true;
  } else if ($type == "image") {
    return true;
  }

  return false;
}

function filterTime($json, $time)
{
  foreach ($json as $key => $value) {
    if ($time > $value->time) {
      unset($json->$key);
    }
  }
  return $json;
}

$app->run();

?>
