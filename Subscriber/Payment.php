<?php

namespace Shopware\Plugins\StripePayment\Subscriber;

use Enlight\Event\SubscriberInterface;

/**
 * The subscriber for adding the custom StripePaymentMethod path.
 *
 * @copyright Copyright (c) 2015, VIISON GmbH
 */
class Payment implements SubscriberInterface
{

	/**
	 * @return An array containing all subsciptions.
	 */
	public static function getSubscribedEvents() {
		return array(
			'Shopware_Modules_Admin_InitiatePaymentClass_AddClass' => 'onAddPaymentClass'
		);
	}

	/**
	 * Adds the path to the Stripe payment method class to the return value,
	 * if a Shopware 5 theme is used in the active shop.
	 *
	 * @param args The arguments passed by the method triggering the event.
	 */
	public function onAddPaymentClass(\Enlight_Event_EventArgs $args) {
		if (Shopware()->Shop()->getTemplate()->getVersion() >= 3) {
			$dirs = $args->getReturn();
			$dirs['StripePaymentMethod'] = 'Shopware\Plugins\StripePayment\Components\StripePaymentMethod';
			$args->setReturn($dirs);
		}
	}

}
