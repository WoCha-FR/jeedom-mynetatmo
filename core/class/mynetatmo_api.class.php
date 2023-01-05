<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class mynetatmo_api {
  const SERVICES_URI=  "https://api.netatmo.com/api";
  const ACCESS_TOKEN_URI=  "https://api.netatmo.com/oauth2/token";

  protected $conf = array();
  protected $access_token;
  protected $refresh_token;
  protected $expires_tstamp;

  protected static $CURL_OPTS = array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HEADER         => FALSE,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_USERAGENT      => 'mynetatmoclient',
    CURLOPT_SSL_VERIFYPEER => TRUE,
    CURLOPT_HTTPHEADER     => array("Accept: application/json"),
  );
  
  /**
   * Initialize a NA OAuth2.0 Client.
   * 
   * @param $config
   * An associative array as below:
   *   - username: (optional) The username.
   *   - password: (optional) The password.
   *   - client_id: (optional) The application ID.
   *   - client_secret: (optional) The application secret.
   *   - access_token: (optional) A stored access_token to use
   *   - refresh_token: (optional) A stored refresh_token to use
   */
  public function __construct($config = array()) {
    // On fourni un token
    if (isset($config['access_token'])) {
      $this->access_token = $config['access_token'];
      unset($config['access_token']);
    }
    if (isset($config['refresh_token'])) {
      $this->refresh_token = $config['refresh_token'];
      unset($config['refresh_token']);
    }
    // Les autres paramètres
    foreach ($config as $name => $value){
      $this->setVar($name, $value);
    }
  }

  /**
   * Make an OAuth2.0 Request.
   */
  public function requestApi($path, $params = array(), $method = 'GET', $renew = true) {
    // On se connecte si besoin
    try {
      $res = $this->getAccessToken();
    } catch (Exception $e) {
      throw $e;
    }
    // On prépare les variables
    if($params == null){
      $params = array();
    }
    foreach ($params as $key => $value){
      if (!is_string($value)){
        $params[$key] = json_encode($value);
      }
    }
    // On lance la requete
    try {
      $res = $this->makeRequest($this->getUri($path), $params, $method);
      log::add('mynetatmo','debug', json_encode($res));
      return $res;
    } catch (Exception $e) {
      // Token expiré ?
      if ($renew == true) {
        // Vérification du code erreur
        if ($e->getCode() == 2 || $e->getCode() == 3) {
          // On retente si refresh_token
          if ($this->refresh_token) {
            try {
              $this->getViaRefreshToken();
            } catch (Exception $ex) {
              //Invalid refresh token
              throw $ex;
            }
          } else {
            throw $e;
          }
          // On relance avec le nouveau Token
          return $this->requestApi($path, $params, $method, false);
        } else {
          throw $e;
        }
      } else {
        throw $e;
      }
    }
  }

  private function makeRequest($path, $params = array(), $method = 'GET') {
    // On init CURL
    $ch = curl_init();
    $opts = array(
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_HEADER         => FALSE,
      CURLOPT_TIMEOUT        => 60,
      CURLOPT_USERAGENT      => 'mynetatmoclient',
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_HTTPHEADER     => array("Accept: application/json"),
    );
    // Mise en forme des paramètres
    if ($params) {
      if ($method == 'GET') {
        $path .= '?' . http_build_query($params, '', '&');
      } else {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params, '', '&');
      }
    }
    // On affecte le path
    $opts[CURLOPT_URL] = $path;
    // Les Headers
    if (isset($opts[CURLOPT_HTTPHEADER])) {
      $existing_headers = $opts[CURLOPT_HTTPHEADER];
      $existing_headers[] = 'Expect:';
      $opts[CURLOPT_HTTPHEADER] = $existing_headers;
    } else {
      $opts[CURLOPT_HTTPHEADER] = array('Expect:');
    }
    // Authorization
    if ($path !== self::ACCESS_TOKEN_URI) {
      if (!$this->access_token) {
        throw new Exception('Access token is missing');
      }
      array_push($opts[CURLOPT_HTTPHEADER], "Authorization: Bearer ".$this->access_token);
    }
    // On affecte les options
    curl_setopt_array($ch, $opts);
    // Execution de la requete
    $result = curl_exec($ch);
    // Si Erreur SSL on relance sans vérifications
    $errno = curl_errno($ch);
    if ($errno == 60 || $errno == 77) {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      $result = curl_exec($ch);
    }
    // Erreur CURL
    if ($result === FALSE) {
      $e = new Exception(curl_errno($ch).' | '.curl_strerror(curl_errno($ch)));
      curl_close($ch);
      throw $e;
    }
    // Traitement retour
    $res_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    // Code Erreur
    if ($res_code != '200') {
      $decode = json_decode($result, true);
      if (!$decode) {
        throw new Exception($res_code. ' => '. $result);
      }
      // Auth Error
      if (isset($decode['error_description'])) {
        throw new Exception($res_code. ' => '. $decode['error'].' : '. $decode['error_description']);
      }
      // Errors with message
      if (isset($decode['error']) && isset($decode['error']['message'])) {
        throw new Exception($res_code. ' => '. $decode['error']['message'] .' ('.$decode['error']['code'].')', intval($decode['error']['code']));
      }
      // Errors Without message
      if (isset($decode['error'])) {
        throw new Exception($res_code. ' => '. $decode['error'], intval($decode['error']['code']));
      }
      return $decode;
    }
    // Code 200
    $decode = json_decode($result, true);
    if (!$decode) {
      throw new Exception($res_code. ' => '. $result);
    }
    // Erreur sur code 200
    if (isset($decode['body']['error'])) {
      throw new Exception($res_code. ' => '. $decode['body']['error']['id'] .' ('.$decode['body']['error']['code'].')');
    }
    // Renvoie des bonnes données
    if (isset($decode['body'])) {
      return $decode['body'];
    }
    return $decode;
  }

  private function getAccessToken() {
    // Token existant
    if ($this->access_token) {
      // Test token expiré ?
      if (!is_null($this->expires_tstamp) && $this->expires_tstamp < time()) {
        // Token expiré et refresh token disponible
        if ($this->refresh_token) {
          return $this->getViaRefreshToken();
        } else if ($this->getVar('username') && $this->getVar('password')) {
          // On se connecte avec utilisateur password
          return $this->getViaCredentials($this->getVar('username'), $this->getVar('password'));
        } else {
          throw new Exception(__('Pas de données pour se connecter.', __FILE__));
        }
      }
      return array('access_token' => $this->access_token);
    }
    // Refresh Token ou Username/Password
    if ($this->refresh_token) {
      return $this->getViaRefreshToken();
    } else if ($this->getVar('username') && $this->getVar('password')) {
      return $this->getViaCredentials($this->getVar('username'), $this->getVar('password'));
    } else {
      throw new Exception(__('Pas de données pour se connecter.', __FILE__));
    }
  }

  private function getViaCredentials($username, $password) {
    // Vérification
    if (($client_id = $this->getVar('client_id')) != null && ($client_secret = $this->getVar('client_secret')) != null) {
      // Make Request
      $scope = $this->getVar('scope');
      $ret = $this->makeRequest(self::ACCESS_TOKEN_URI, array(
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => $password,
        'scope' => $scope
        ),'POST');
      $this->setToken($ret);
      return $ret;
    } else {
      throw new Exception(__('Paramètres manquant pour se connecter.', __FILE__));
    }
  }

  private function getViaRefreshToken() {
    // Vérification
    if (($client_id = $this->getVar('client_id')) != null && ($client_secret = $this->getVar('client_secret')) != null && $this->refresh_token != null) {
      $ret = $this->makeRequest(self::ACCESS_TOKEN_URI, array(
        'grant_type' => 'refresh_token',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'refresh_token' => $this->refresh_token
        ),'POST');
      $this->setToken($ret);
      return $ret;
    } else {
      throw new Exception(__('Paramètres manquant pour se connecter.', __FILE__));
    }
  }

  private function setToken($value) {
    if(isset($value['access_token'])){
      $this->access_token = $value['access_token'];
      config::save('access_token', $value['access_token'],'mynetatmo');
    }
    if(isset($value['refresh_token'])){
      $this->refresh_token = $value['refresh_token'];
      config::save('refresh_token', $value['refresh_token'],'mynetatmo');
    }
    if(isset($value['expires_in'])){
      $this->expires_tstamp = time() + $value['expires_in'] - 60;
    }
  }

  private function setVar($name, $value) {
    $this->conf[$name] = $value;
    return $this;
  }

  private function getVar($name, $default = null){
    return isset($this->conf[$name]) ? $this->conf[$name] : $default;
  }

  protected function getUri($path = '') {
    $url = self::SERVICES_URI;
    // path = URL ?
    if (!empty($path)){
      if (substr($path, 0, 4) == "http"){
        $url = $path;
      } else if (substr($path, 0, 5) == "https"){
        $url = $path;
      } else {
        $url = rtrim($url, '/') . '/' . ltrim($path, '/');
      }
    }
    // Renvoie URL mise en forme
    return $url;
  }

}