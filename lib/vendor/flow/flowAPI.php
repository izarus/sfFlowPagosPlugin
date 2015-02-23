<?php

/*******************************************************************************
* flowAPI                                                                       *
*                                                                               *
* Version: 1.1                                                                  *
* Date:    2015-02-13                                                          *
* Author:  flow.cl
* Modified by: izarus.cl  (23-02-2015)                                          *
********************************************************************************/

class flowAPI {

  protected $order = array();

  //Constructor de la clase
  function __construct() {
    $this->order["OrdenNumero"] = "";
    $this->order["Concepto"]    = "";
    $this->order["Monto"]       = "";
    $this->order["Comision"]    = sfConfig::get('app_flowpagos_tasa_default',2);
    $this->order["FlowNumero"]  = "";
    $this->order["Pagador"]     = "";
    $this->order["Status"]      = "";
    $this->order["Error"]       = "";
  }

  // Metodos SET

  /**
  * Set el número de Orden del comercio
  * @param string $orderNumer El número de la Orden del Comercio
  * @return bool (true/false)
  */
  public function setOrderNumber($orderNumber) {
    if(!empty($orderNumber)) {
      $this->order["OrdenNumero"] = $orderNumber;
    }
    $this->flow_log("Asigna Orden N°: ". $this->order["OrdenNumero"], '');
    return !empty($orderNumber);
  }

  /**
  * Set el concepto de pago
  * @param string $concepto El concepto del pago
  * @return bool (true/false)
  */
  public function setConcept($concepto) {
    if(!empty($concepto)) {
      $this->order["Concepto"] = $concepto;
    }
    return !empty($concepto);
  }

  /**
  * Set el monto del pago
  * @param string $monto El monto del pago
  * @return bool (true/false)
  */
  public function setAmount($monto) {
    if(!empty($monto)) {
      $this->order["Monto"] = $monto;
    }
    return !empty($monto);
  }

  /**
  * Set la tasa de comisión
  * @param int $comision La comisión Flow del pago
  * @return bool (true/false)
  */
  public function setRate($comision) {
    if(!empty($comision) && ($comision == 1 || $comision == 2 || $comision == 3)) {
      $this->order["Comision"] = $comision;
      return TRUE;
    } else {
      return FALSE;
    }
  }


  // Metodos GET

  /**
  * Get el número de Orden del Comercio
  * @return string el número de Orden del comercio
  */
  public function getOrderNumber() {
    return $this->order["OrdenNumero"];
  }

  /**
  * Get el concepto de Orden del Comercio
  * @return string el concepto de Orden del comercio
  */
  public function getConcept() {
    return $this->order["Concepto"];
  }

  /**
  * Get el monto de Orden del Comercio
  * @return string el monto de la Orden del comercio
  */
  public function getAmount() {
    return $this->order["Monto"];
  }

  /**
  * Get la comisión Flow de Orden del Comercio
  * @return string la tasa de la Orden del comercio
  */
  public function getRate() {
    return $this->order["Comision"];
  }

  /**
  * Get el estado de la Orden del Comercio
  * @return string el estado de la Orden del comercio
  */
  public function getStatus() {
    return $this->order["Status"];
  }

  /**
  * Get el número de Orden de Flow
  * @return string el número de la Orden de Flow
  */
  public function getFlowNumber() {
    return $this->order["FlowNumero"];
  }

  /**
  * Get el email del pagador de la Orden
  * @return string el email del pagador de la Orden de Flow
  */
  public function getPayer() {
    return $this->order["Pagador"];
  }


  /**
  * Crea una nueva Orden para ser enviada a Flow
  * @param string $orden_compra El número de Orden de Compra del Comercio
  * @param string $monto El monto de Orden de Compra del Comercio
  * @param string $concepto El concepto de Orden de Compra del Comercio
  * @param mixed $tipo_comision La comision de Flow (1,2,3)
  * @return string flow_pack Paquete de datos firmados listos para ser enviados a Flow
  */
  public function new_order($orden_compra, $monto,  $concepto, $email_pagador, $tipo_comision = "Non") {
    $this->flow_log("Iniciando nueva Orden", "new_order");
    if(!isset($orden_compra,$monto,$concepto)) {
      $this->flow_log("Error: No se pasaron todos los parámetros obligatorios","new_order");
    }
    if($tipo_comision == "Non") {
      $tipo_comision = sfConfig::get('app_flowpagos_tasa_default',2);
    }
    if(!is_numeric($monto)) {
      $this->flow_log("Error: El parámetro monto de la orden debe ser numérico","new_order");
      throw new Exception("El monto de la orden debe ser numérico");
    }
    $this->order["OrdenNumero"] = $orden_compra;
    $this->order["Concepto"] = $concepto;
    $this->order["Monto"] = $monto;
    $this->order["Comision"] = $tipo_comision;
    $this->order["Pagador"] = $email_pagador;
    return $this->flow_pack();
  }

  /**
  * Lee los datos enviados desde Flow a la página de confirmación del comercio
  */
  public function read_confirm() {
    if(!isset($_POST['response'])) {
      $this->flow_log("Respuesta Inválida", "read_confirm");
      throw new Exception('Invalid response');
    }
    $data = $_POST['response'];
    $params = array();
    parse_str($data, $params);
    if(!isset($params['status'])) {
      $this->flow_log("Respuesta sin status", "read_confirm");
      throw new Exception('Invalid response status');
    }
    $this->order['Status'] = $params['status'];
    $this->flow_log("Lee Status: " . $params['status'], "read_confirm");
    if (!isset($params['s'])) {
      $this->flow_log("Mensaje no tiene firma", "read_confirm");
      throw new Exception('Invalid response (no signature)');
    }
    if(!$this->flow_sign_validate($params['s'], $data)) {
      $this->flow_log("firma invalida", "read_confirm");
      throw new Exception('Invalid signature from Flow');
    }
    $this->flow_log("Firma verificada", "read_confirm");
    if($params['status'] == "ERROR") {
      $this->flow_log("Error: " .$params['kpf_error'], "read_confirm");
      $this->order["Error"] = $params['kpf_error'];
      return;
    }
    if(!isset($params['kpf_orden'])) {
      throw new Exception('Invalid response Orden number');
    }
    $this->order['OrdenNumero'] = $params['kpf_orden'];
    $this->flow_log("Lee Numero Orden: " . $params['kpf_orden'], "read_confirm");
    if(!isset($params['kpf_monto'])) {
      throw new Exception('Invalid response Amount');
    }
    $this->order['Monto'] = $params['kpf_monto'];
    $this->flow_log("Lee Monto: " . $params['kpf_monto'], "read_confirm");
    if(isset($params['kpf_flow_order'])) {
      $this->order['FlowNumero'] = $params['kpf_flow_order'];
      $this->flow_log("Lee Orden Flow: " . $params['kpf_flow_order'], "read_confirm");
    }

  }


  /**
  * Método para responder a Flow el resultado de la confirmación del comercio
  * @param bool $result (true: Acepta el pago, false rechaza el pago)
  * @return string paquete firmado para enviar la respuesta del comercio
  */
  public function build_response($result){
    global $flow_comercio;
    $r = ($result) ? "ACEPTADO" : "RECHAZADO";
    $data = array();
    $data["status"] = $r;
    $data["c"] = sfConfig::get('app_flowpagos_comercio');
    $q = http_build_query($data);
    $s = $this->flow_sign($q);
    $this->flow_log("Orden N°: ".$this->order["OrdenNumero"]. " - Status: $r","flow_build_response");
    return $q."&s=".$s;
  }

  /**
  * Método para recuperar los datos  en la página de Exito o Fracaso del Comercio
  */
  public function read_result() {
    if(!isset($_POST['response'])) {
      $this->flow_log("Respuesta Inválida", "read_result");
      throw new Exception('Invalid response');
    }
    $data = $_POST['response'];
    $params = array();
    parse_str($data, $params);
    if (!isset($params['s'])) {
      $this->flow_log("Mensaje no tiene firma", "read_result");
      throw new Exception('Invalid response (no signature)');
    }
    if(!$this->flow_sign_validate($params['s'], $data)) {
      $this->flow_log("firma invalida", "read_result");
      throw new Exception('Invalid signature from Flow');
    }
    $this->order["Comision"] = sfConfig::get('app_flowpagos_tasa_default',2);
    $this->order["Status"] = "";
    $this->order["Error"] = "";
    $this->order['OrdenNumero'] = $params['kpf_orden'];
    $this->order['Concepto'] = $params['kpf_concepto'];
    $this->order['Monto'] = $params['kpf_monto'];
    $this->order["FlowNumero"] = $params["kpf_flow_order"];
    $this->order["Pagador"] = $params["kpf_pagador"];
    $this->flow_log("Datos recuperados Orden de Compra N°: " .$params['kpf_orden'], "read_result");
  }

  /**
  * Registra en el Log de Flow
  * @param string $message El mensaje a ser escrito en el log
  * @param string $type Identificador del mensaje
  */
  public function flow_log($message, $type) {
    $file = @fopen(sfConfig::get('app_flowpagos_log_path') . "/flowLog_" . date("Y-m-d") .".txt" , "a+");
    @fwrite($file, "[".date("Y-m-d H:i:s.u")." ".$_SERVER['REMOTE_ADDR']." - $type ] ".$message . PHP_EOL);
    @fclose($file);
  }


  // Funciones Privadas
  private function flow_get_public_key_id() {
    $flow_public_key = __DIR__."/flow.pubkey";
    try {
      $fp = fopen($flow_public_key, "r");
      $pub_key = fread($fp, 8192);
      fclose($fp);
      return openssl_get_publickey($pub_key);
    } catch (Exception $e) {
      $this->flow_log("Error al intentar obtener la llave pública - Error-> " .$e->getMessage(), "flow_get_public_key_id");
      throw new Exception($e->getMessage());
    }
  }

  private function flow_get_private_key_id() {

    if (!sfConfig::get('app_flowpagos_key')) {
      throw new Exception("Clave privada de Comercio Flow no definida.");
    }

    if (!is_file(sfConfig::get('app_flowpagos_key'))) {
      throw new Exception("Clave privada de Comercio Flow no se puede leer.");
    }

    try {
      $fp = fopen(sfConfig::get('app_flowpagos_key'), "r");
      $priv_key = fread($fp, 8192);
      fclose($fp);
      return openssl_get_privatekey($priv_key);
    } catch (Exception $e) {
      $this->flow_log("Error al intentar obtener la llave privada - Error-> " .$e->getMessage(), "flow_get_private_key_id");
      throw new Exception($e->getMessage());
    }
  }

  private function flow_sign($data) {
    $priv_key_id = $this->flow_get_private_key_id();
    if(!openssl_sign($data, $signature, $priv_key_id)) {
      $this->flow_log("No se pudo firmar", "flow_sign");
      throw new Exception('It can not sign');
    };
    return base64_encode($signature);
  }

  private function flow_sign_validate($signature, $data) {
    $signature = base64_decode($signature);
    $response = explode("&s=", $data, 2);
    $response = $response[0];
    $pub_key_id = $this->flow_get_public_key_id();
    return (openssl_verify($response, $signature, $pub_key_id) == 1);
  }

  private function flow_pack() {
    $comercio = urlencode(sfConfig::get('app_flowpagos_comercio'));
    $orden_compra = urlencode($this->order["OrdenNumero"]);
    $monto = urlencode($this->order["Monto"]);
    $tipo_comision = urlencode($this->order["Comision"]);
    $concepto = urlencode(htmlentities(utf8_decode($this->order["Concepto"])));
    $url_exito = urlencode(sfConfig::get('app_flowpagos_url_exito'));
    $url_fracaso = urlencode(sfConfig::get('app_flowpagos_url_fracaso'));
    $url_confirmacion = urlencode(sfConfig::get('app_flowpagos_url_confirmacion'));
    $tipo_integracion = urlencode(sfConfig::get('app_flowpagos_tipo_integracion','f'));
    $email = urlencode($this->order["Pagador"]);
    $hConcepto = htmlentities($this->order["Concepto"]);
    if (!$hConcepto) $hConcepto = htmlentities($concepto, ENT_COMPAT | ENT_HTML401, 'UTF-8');
    if (!$hConcepto) $hConcepto = htmlentities($concepto, ENT_COMPAT | ENT_HTML401, 'ISO-8859-1');
    if (!$hConcepto) $hConcepto = "Orden de Compra $orden_compra";
    $concepto = urlencode($hConcepto);
    $p = "c=$comercio&oc=$orden_compra&tc=$tipo_comision&m=$monto&o=$concepto&ue=$url_exito&uf=$url_fracaso&uc=$url_confirmacion&ti=$tipo_integracion&e=$email&v=kit_1_1";
    $signature = $this->flow_sign($p);
    $this->flow_log("Orden N°: ".$this->order["OrdenNumero"]. " - empaquetado correcto","flow_pack");
    return $p."&s=$signature";
  }

}
