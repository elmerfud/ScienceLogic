<?php

Class Em7 {

  var $base = null;
  var $uri_method = 'GET';
  var $uri = null;
  var $uri_called = '';
  var $uri_vars = array();
  var $func_callback = null;
  var $methods = array();
  var $method_chain = array();

  public function __construct($config = array()) {
    if ( (isset($config['uri']) === false) ||
         (isset($config['username']) === false) ||
         (isset($config['password']) === false) )  {
      throw new Exception("You must specify the config information as array('uri' => <EM7 API URL>, 'username' => <EM7 USERNAME>, 'password' => <EM7 PASSWORD>)");
    }
    $this->base = $config;
    $this->grab_methods();

    return true;
  }

  public function __call($name, $arg = null) 
  {
    if (count($this->method_chain) == 0) {
      if (isset($this->methods[$name]) === false) {
        $this->clear_old();
        throw new Exception("Method unknown");
      } else {
        $this->clear_old();
      }
      $this->method_chain[] = $name;
    } else {
          
    }
    if (is_array($arg) === true) {
      if ((isset($arg[0]) === true)&&(is_numeric($arg[0]) === true)) {
        $this->uri_called = $this->uri_called . "/{$name}/{$arg[0]}";
      } else {
        $this->uri_called = $this->uri_called . "/{$name}";
      }
    }
    //var_dump($name); var_dump($arg);
    return $this;
  }

  private function grab_methods()
  {
    $response = $this->perform_request();
    if (($response['http_code'] !== 200)||(isset($response['content']) === false)) {
      $this->clear_old();
      throw new Exception("Unable to build methods");
    }
    foreach ($response['content'] as $row) {
      $method = substr($row['URI'],5);
      $this->methods[$method] = array();
    }

    //var_dump($this->methods);
  }

  public function callback($name)
  {
    $this->func_callback = $name;
    return $this;
  }

  private function clear_old()
  {
    // Clear any old vars
    $this->uri_called = '';
    $this->uri_vars = array();
    $this->func_callback = null;
    $this->method_chain = array();
    return true;
  }

  public function limit($num = 100)
  {
    $this->uri_vars['limit'] = $num;
    return $this;
  }

  public function offset($num = 0)
  {
    $this->uri_vars['offset'] = $num;
    return $this;
  }

  public function filter($filter = null, $value = null, $ops = null)
  {
    if (is_array($ops) === true) {
      $ops = "." . implode('.',$ops);
    }
    $this->uri_vars["filter.{$filter}{$ops}"] = $value;
    return $this;
  }

  public function method($method = 'GET')
  {
    $this->uri_method = $method;
    return $this;
  }

  public function apply($data)
  {
    $this->method('APPLY');

    $this->uri = $this->uri_called;
    $response = $this->perform_request($data);
    if ($response['http_code'] == 201) {
      if (is_callable(array($this,"{$this->callback}_create")) === true) {
        return $this->{"{$this->callback}_create"}($response['headers']['Location']);
      } else {
        return $response['headers']['Location'];
      }
    } elseif (($response['http_code'] == 200)||($response['http_code'] == 202)) {
      return true;
    } else {
      $error_message = "Could not create " . substr($this->uri, 1) . ". ";
      if  (array_key_exists("error", $response)) {
        $error_message .= $response['error'];
      }
      $this->clear_old();
      throw new Exception($error_message);
    }

  }

  public function delete()
  {
    $this->method('DELETE');

    $this->uri = $this->uri_called;
    $response = $this->perform_request();
    if ($response['http_code'] == 200 || $response['http_code'] == 302) {
      return true;
    } else {
      $error_message = "Could not delete " . substr($this->uri, 1) . ". ";
      if  (array_key_exists("error", $response)) {
        $error_message .= $response['error'];
      }
      $this->clear_old();
      throw new Exception($error_message);
    }
  }

  public function post($array = array())
  {
    $this->method('POST');

    $this->uri = $this->uri_called;
    $response = $this->perform_request($array);
    if ($response['http_code'] == 201) {
      if (is_callable(array($this,"{$this->callback}_create")) === true) {
        return $this->{"{$this->callback}_create"}($response['headers']['Location']);
      } else {
        return $response['headers']['Location'];
      }
    } elseif ($response['http_code'] == 200) {
      return true;
    } else {
      $error_message = "Could not create " . substr($this->uri, 1) . ". ";
      if  (array_key_exists("error", $response)) {
        $error_message .= $response['error'];
      }
      $this->clear_old();
      throw new Exception($error_message);
    }
  }

  public function get($all = false)
  {
    $this->method();

    if ($all === true) {
      if (isset($this->uri_vars['limit']) === false) {
        $this->limit();
      }
      if (isset($this->uri_vars['offset']) === false) {
        $this->offset();
      }
    }
    $entities = array();
    do {
      $this->uri = $this->uri_called . "?" . http_build_query($this->uri_vars);
      $response = $this->perform_request();
      if ($response['http_code'] == 200) {
        if (array_key_exists("result_set", $response['content']) === true) {
          if (count($response['content']['result_set']) > 0) {
            $entities = array_merge($entities, $response['content']['result_set']);
          } else {
            $this->clear_old();
            throw new Exception("No matched entries");
          }
        } else {
          $entities = $response['content'];
        }
      } elseif ($response['http_code'] != 200) {
        $message = "An error occured while requesting entities. ";
        if(array_key_exists("error", $response)) {
          $message .= $response['error'];
        }
        $this->clear_old();
        throw new Exception($message);
      }
      if ($all === false) {
        break;
      }
      $this->uri_vars['offset'] = $this->uri_vars['offset'] + $this->uri_vars['limit'];

    } while(!isset($message) AND ($this->uri_vars['offset'] < $response['content']['total_matched']));

    return $this->return_entities($entities);
  }

  private function perform_request($content = FALSE)
  {
    //TODO, validate method chain

    $uri = $this->base['uri'] . $this->uri;
    $ch = curl_init($uri);

    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, $this->base['username'] . ":" . $this->base['password']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

    if ($this->uri_method == "POST" && $content) {
      $json_content = json_encode($content);
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json_content);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('content-type: application/json'));
    }

    if ($this->uri_method == "APPLY" && $content) {
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('content-type: application/em7-resource-uri'));
    }

    if ($this->uri_method == "DELETE") {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    } elseif (($this->uri_method == "POST" OR $this->uri_method == "APPLY") AND !$content) {
      return FALSE;
    }

    $output = curl_exec($ch);

    $response['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response['contenttype'] = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    $output_array = explode("\n", $output);
    array_shift($output_array);

    foreach ($output_array as $line) {
      if (strpos($line, "{") < 2 AND strpos($line, "{") !== FALSE) {
        $response['content'] = json_decode($line, TRUE);
      } elseif (strpos($line, ":") !== FALSE) {
        $header_array = explode(":", $line);
        $response['headers'][$header_array[0]] = trim($header_array[1]);
      }
    }

    if (!array_key_exists('content', $response)) {
      $response['content'] = array();
    }
    if ($response['http_code'] > 300) {
      $response['error'] = "HTTP status " . $response['http_code'] . " returned. ";
      if (array_key_exists("X-EM7-status-message", $response['headers'])) {
        $response['error'] .= $response['headers']['X-EM7-status-message'] . ". ";
      }
      if (array_key_exists("X-EM7-info-message", $response['headers'])) {
        $response['error'] .= $response['headers']['X-EM7-info-message'] . ". ";
      }
    }
    curl_close($ch);
    return $response;

  }

  private function return_entities($entities)
  {
    $out = null;
    if (method_exists($this,"{$this->func_callback}") === true) {
      $out = $this->{"{$this->func_callback}"}($entities);
    } elseif (method_exists('self',$this->func_callback) === true) {
      $out = self::$this->func_callback($entities);
    } elseif (is_callable("{$this->func_callback}") === true) {
      $out = call_user_func($this->func_callback,$entities);
    } else {
      $out = $entities;
    }
    $this->clear_old();
    return $out;

  }

}
?>
