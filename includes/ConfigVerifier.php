<?php

class ConfigVerifier {
	public function getPricingErrors(array $normalized) {
		$i18n = Localization::getTranslator();
		$pricingSettings = [];
		$cfg = new Config();
		$priceMSG = null;
		try {
			$price = ServiceLocater::resolve(
				$normalized,
				'PricingProvider'
			)->getPrice();
			if (!is_numeric($price->get())) {
				$pricingSettings[] = [
					'id' => '#sources-methods-error',
					'error' => $i18n->_('Unknown format encountered:') . ' "' . $price . '".',
				];
			}
			$priceMSG = $i18n->_('Current Price would be: ')
				. $cfg->getCurrencyMeta()->format($price);
		} catch (Exception $e) {
			$pricingSettings[] = [
				'id' => '#sources-methods-error',
				'error' => $e->getMessage(),
			];
		}
		
		return [
			'errors' => $pricingSettings,
			'price' => $priceMSG
		];
	}
	
	public function getPricingErrorsFromConfig(Config $cfg) {
		$i18n = Localization::getTranslator();
		$pricingSettings = [];
		try {
			$price = $cfg->getPricingProvider()->getPrice();
			if (!is_numeric($price->get())) {
				$pricingSettings[] = [
					'id' => '#sources-methods-error',
					'error' => $i18n->_('Unknown format encountered:') . ' "' . $price . '".',
				];
			}
		} catch (Exception $e) {
			$pricingSettings[] = [
				'id' => '#sources-methods-error',
				'error' => $e->getMessage(),
			];
		}
		
		return $pricingSettings;
	}
	
	public function getErrors(Config $cfg) {
		$i18n = Localization::getTranslator();
		$walletSettings = [];
		$emailSettings = [];
		
		try {
			$cfg->getWalletProvider()->verifyOwnership();
		} catch (Exception $e) {
		  error_log("Could not verify wallet ownership.");
			$walletSettings[] = [
				'id' => '#wallet-id-error',
				'error' => $e->getMessage(),
			];
		}
		
		try {
			$t = new Swift_SmtpTransport('smtp.gmail.com',
			   465,
			   'ssl');
			$t->setUsername($cfg->getEmailUsername())
				->setPassword($cfg->getEmailPassword())
				->start();
		} catch (Exception $e) {
		  error_log("Failed on email configuration.");
			$emailSettings[] = [
				'id' => '#email-username-error',
				'error' => $e->getMessage(),
			];
		}
		
		$errors = [];
		
		if (!empty($pricingSettings)) {
			$errors['#pricing-settings'] = self::getPricingErrorsFromConfig($cfg);
			error_log("Price Settings Validation Error");
		}
		
		if (!empty($walletSettings)) {
			$errors['#wallet-settings'] = $walletSettings;
		}
		
		if (!empty($emailSettings)) {
			$errors['#email-settings'] = $emailSettings;
		}
		
		return $errors;
	}
}
