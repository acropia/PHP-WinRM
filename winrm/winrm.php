<?php

class Response {

	// Reponse - __repr__
	public function toString() {
		return "<Response code nogwat";
	}
}

class Session {
	protected $protocol;
	protected $url;

	public function __construct($target, $username=null, $password=null, $transport='plaintext') {
		print (__METHOD__."()\n");
		$this->url = self::buildUrl($target, $transport);
		$this->protocol = new Protocol($this->url, $transport, $username, $password);
	}

	public static function buildUrl($target, $transport) {
		print (__METHOD__."()\n");
		print (__METHOD__.": - target: ".$target."\n");
		print (__METHOD__.": - transport: ".$transport."\n");

		$targetParts = parse_url($target);

		if (isset($targetParts['scheme'])) {
			$scheme = $targetParts['scheme'];
		}
		else {
			$scheme = ($transport == 'ssl') ? 'https' : 'http';
		}

		$host = $targetParts['host'];

		if (isset($targetParts['port'])) {
			$port = $targetParts['port'];
		}
		else {
			$port = ($transport == 'ssl') ? 5986 : 5985;
		}

		if (isset($targetParts['path'])) {
			$path = $targetParts['path'];
			$path = ltrim($path, '/');
		}
		else {
			$path = 'wsman';
		}

		$ret = $scheme."://".$host.":".$port."/".$path;
		print (__METHOD__.": return: ".$ret."\n");
		return $ret;
	}

	public function runCommand($command) {
		print (__METHOD__."()\n");
		$args = '';
		$shellId = $this->protocol->openShell();
		$commandId = $this->protocol->runCommand($shellId, $command, $args);
		$rs = Response($this->protocol->getCommandOutput($shellId, $commandId));
		$this->protocol->cleanupCommand($shellId, $commandId);
		$this->protocol->closeShell($shellId);
		return $rs;
	}
}
