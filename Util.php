<?php

namespace Shopware\Plugins\StripePayment;

use Stripe;

/**
 * Utility functions used across this plugin.
 *
 * @copyright Copyright (c) 2015, VIISON GmbH
 */
class Util
{

	/**
	 * This field is used as a cache for the Stripe customer object of the
	 * currently logged in user.
	 *
	 * @var Stripe_Customer
	 */
	private static $stripeCustomer;

	/**
	 * Sets the Stripe secret key.
	 */
	public static function initStripeAPI() {
		// Set the Stripe API key
		$stripeSecretKey = self::stripeSecretKey();
		Stripe\Stripe::setApiKey($stripeSecretKey);

		// Set API version manually to make all plugin versions working, no matter which
		// version is selected in the Stripe app settings
		Stripe\Stripe::setApiVersion('2015-10-01');
	}

	/**
	 * @return The Stripe public key set in the plugin configuration.
	 */
	public static function stripePublicKey() {
		return Shopware()->Plugins()->Frontend()->StripePayment()->Config()->get('stripePublicKey');
	}

	/**
	 * @return The Stripe secret key set in the plugin configuration.
	 */
	public static function stripeSecretKey() {
		return Shopware()->Plugins()->Frontend()->StripePayment()->Config()->get('stripeSecretKey');
	}

	/**
	 * Uses the Stripe customer id of the active user to retrieve the customer from Stripe
	 * and returns the customer's credit cards.
	 *
	 * @return An array containing information about all loaded credit cards.
	 * @throws An exception, if loading the Stripe customer fails.
	 */
	public static function getAllStripeCards() {
		// Get the Stripe customer
		$customer = self::getStripeCustomer();
		if ($customer === null || $customer->deleted) {
			return array();
		}

		// Get information about all cards
		$cards = array();
		foreach ($customer->sources->data as $card) {
			$cards[] = self::convertStripeCardToArray($card);
		}

		// Sort the cards by id (which correspond to the date, the card was created/added)
		usort($cards, function($cardA, $cardB) {
			return strcmp($cardA['id'], $cardB['id']);
		});

		return $cards;
	}

	/**
	 * Uses the Stripe customer id of the active user to retrieve the customer from Stripe
	 * and returns the customer's default credit card.
	 *
	 * @return The default credit card or null, if no cards exist.
	 * @throws An exception, if loading the Stripe customer fails.
	 */
	public static function getDefaultStripeCard() {
		// Get the Stripe customer
		$customer = self::getStripeCustomer();
		if ($customer === null || $customer->deleted) {
			return null;
		}

		// Get all cards and try to find the one matching the default id
		$cards = self::getAllStripeCards();
		foreach ($cards as $card) {
			if ($card['id'] === $customer->default_source) {
				// Return the default card
				return $card;
			}
		}

		return null;
	}

	/**
	 * First tries to find currently logged in user in the database and checks their stripe customer id.
	 * If found, the customer information is loaded from Stripe and returned.
	 *
	 * @return The customer, which was loaded from Stripe or null, if e.g. the customer does not exist.
	 * @throws An exception, if Stripe could not load the customer.
	 */
	public static function getStripeCustomer() {
		self::initStripeAPI();
		// Check if customer is already loaded
		if (self::$stripeCustomer !== null) {
			return self::$stripeCustomer;
		}

		// Get the current logged in customer
		$customer = self::getCustomer();
		if ($customer === null || $customer->getAccountMode() === 1 || $customer->getAttribute() === null || $customer->getAttribute()->getStripeCustomerId() === null) {
			// Customer not found, without permanent user account or has no stripe customer associated with it
			return null;
		}

		// Load, save and return the customer
		$stripeCustomerId = $customer->getAttribute()->getStripeCustomerId();
		self::$stripeCustomer = Stripe\Customer::retrieve($stripeCustomerId);

		return self::$stripeCustomer;
	}

	/**
	 * Checks if a user/customer is currently logged in and tries to get and return that customer.
	 *
	 * @return The customer object of the user who is logged in, or null, if no user is logged in.
	 */
	public static function getCustomer() {
		// Check if a user is logged in
		if (empty(Shopware()->Session()->sUserId)) {
			return null;
		}

		// Try to find the customer
		$customerId = Shopware()->Session()->sUserId;
		if ($customerId === null) {
			return null;
		}
		$customerRepository = Shopware()->Models()->getRepository('\Shopware\Models\Customer\Customer');
		$customer = $customerRepository->find($customerId);

		return $customer;
	}

	/**
	 * @return The customers company name, if it exists. Otherwise their joined first and last name or null, if no user is logged in.
	 */
	public static function getCustomerName() {
		$customer = self::getCustomer();
		if ($customer === null) {
			return null;
		}

		// Check for company
		$company = $customer->getBilling()->getCompany();
		if (!empty($company)) {
			return $company;
		}

		// Use first and last name
		return trim($customer->getBilling()->getFirstName() . ' ' . $customer->getBilling()->getLastName());
	}

	/**
	 * First tries to get an existing Stripe customer. If it exists, the transaction token is used
	 * to add a new card to that customer. If not, a new Stripe customer is created and added the
	 * card represented by the transaction token.
	 *
	 * @param transactionToken The token, which will be used to add/create a new Stripe card.
	 * @return An array containing the data of the created Stripe card.
	 */
	public static function saveStripeCard($transactionToken) {
		self::initStripeAPI();
		// Get the Stripe customer
		$stripeCustomer = self::getStripeCustomer();
		if ($stripeCustomer !== null && !$stripeCustomer->deleted) {
			// Add the card to the existing customer
			$newCard = $stripeCustomer->sources->create(array(
				'source' => $transactionToken
			));
		} else {
			// Get the currently active user
			$customer = self::getCustomer();
			if ($customer === null || $customer->getAccountMode() === 1) {
				// Customer not found or without permanent user account
				return null;
			}

			// Create a new Stripe customer and add the card to them
			$stripeCustomer = Stripe\Customer::create(array(
				'description' => self::getCustomerName(),
				'email' => $customer->getEmail(),
				'source' => $transactionToken
			));
			$newCard = $stripeCustomer->sources->data[0];

			// Save the Stripe customer id
			$customer->getAttribute()->setStripeCustomerId($stripeCustomer->id);
			Shopware()->Models()->flush($customer->getAttribute());
		}

		// Return the created Stripe card array
		return self::convertStripeCardToArray($newCard);
	}

	/**
	 * Tries to delete the Stripe card with the given id from
	 * the Stripe customer, which is associated with the currently
	 * logged in user.
	 *
	 * @param $cardId The Stripe id of the card, which shall be deleted.
	 */
	public static function deleteStripeCard($cardId) {
		self::initStripeAPI();
		// Get the Stripe customer
		$customer = self::getStripeCustomer();
		if ($customer === null) {
			return;
		}

		// Delete the card with the given id from Stripe
		$customer->sources->retrieve($cardId)->delete();
	}

	/**
	 * Converts the given Stripe card instance to an array.
	 *
	 * @param Stripe\Card $card
	 * @return array
	 */
	private static function convertStripeCardToArray($card) {
		return array(
			'id' => $card->id,
			'name' => $card->name,
			'brand' => $card->brand,
			'last4' => $card->last4,
			'exp_month' => $card->exp_month,
			'exp_year' => $card->exp_year
		);
	}

}
