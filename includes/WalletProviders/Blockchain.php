<?php

namespace WalletProviders;
use BitcoinTransactions\BlockchainTransaction;
use BitcoinAddress;
use JSON;
use Exception;
use Exceptions\InsufficientFundsException;
use Amount;
use SimpleHTTP;

class Blockchain implements \WalletProvider {
	private $mainPass;
	private $secondPass;
	private $id;
	private $fromAddress;

	//https://blockchain.info/api/blockchain_wallet_api
	private static $URL = "http://localhost:3000/merchant/";
	//private static $URL = "https://blockchain.info/merchant/";

	private static $API_CODE = "ba11d83c-5390-467d-8fa9-c1b9d338faf1";
	
	
	private function baseURL() {
		return self::$URL . substr(urlencode($this->id), 0, 36) . '/';
	}
	
	public function configure(array $options) {
		try {
			$this->fromAddress = new BitcoinAddress($options['fromAddress']);
		} catch(\InvalidArgumentException $e) {
			throw new \ConfigurationException($e->getMessage());
		}
		
		$this->mainPass = $options['mainPass'];
		$this->secondPass = $options['secondPass'];
		$this->id = $options['id'];
	}
	
	public function verifyOwnership() {
		        $from_addr =  $this->fromAddress->get();

	       $request = $this->baseURL() . 'list?' . http_build_query([
			'password' => $this->mainPass,
			'api_code' => self::$API_CODE
		]);

		try {
			$get = SimpleHTTP::get($request);
		} catch (Exception $e) {
		  error_log("Error making a request.");
			throw new Exception("There was a network error while processing the request.");
		}
		
		$decoded = JSON::decode($get);
		
		if (isset($decoded['error'])) {
			error_log('Blockchain.info responded with: ' . $decoded['error']);
			throw new Exception('Blockchain.info responded with: ' . $decoded['error']);
		}
		
		return true;
	}
	
	public function getBalance($confirmations = 1) {
		$request = 'https://blockchain.info/unspent?' . http_build_query([
			'active' => $this->fromAddress->get()
		]);
		
		try {
			$get = SimpleHTTP::get($request);
		} catch (Exception $e) {
			throw new Exception("There was a network error while processing the request.");
		}
		
		$decoded = JSON::decode($get);
		$balance = new Amount('0');
		
		foreach ($decoded['unspent_outputs'] as $output) {
			if (!empty($output['confirmations'])
			&& $output['confirmations'] >= $confirmations) {
				$balance = $balance->add(Amount::fromSatoshis(
					$output['value']
				));
			}
		}
		
		if (isset($decoded['error'])) {
			throw new Exception('Blockchain.info responded with: ' . $decoded['error']);
		}
		
		return $balance;
	}
	
	public function isConfigured() {
		return isset(
			$this->mainPass,
			$this->secondPass,
			$this->id,
			$this->fromAddress
		);
	}
	
	public function sendTransaction(BitcoinAddress $to, Amount $howMuch) {
		if ($this->getBalance()->isLessThan($howMuch)) {
			throw new InsufficientFundsException();
		}
		
		$request = $this->baseURL() . 'payment?' . http_build_query([
			'password' => $this->mainPass,
			'second_password' => $this->secondPass,
			'from' => $this->fromAddress->get(),
			'to' => $to->get(),
			'amount' => $howMuch->toSatoshis()->get(),
			'api_code' => self::$API_CODE
		]);
		
		try {
			$encoded = SimpleHTTP::get($request);
		} catch (Exception $e) {
			throw new Exception("There was a network error while processing the request.");
		}
		
		$decoded = JSON::decode($encoded);
		
		if (isset($decoded['error'])) {
			throw new Exception('Blockchain.info responded with: ' . $decoded['error']);
		}
		
		return new BlockchainTransaction($decoded);
	}
}



