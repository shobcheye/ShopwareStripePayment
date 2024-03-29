{extends file="frontend/account/index.tpl"}

{namespace name='frontend/plugins/stripe_payment/account'}

{* Breadcrumb *}
{block name="frontend_index_start" append}
	{$sBreadcrumb[] = ["name" => "{s name='credit_cards/title'}{/s}", "link" => {url}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
	<div class="content account--content">
		{* Error handling *}
		{capture name="stripeErrorTitleCapture"}
			{s name="credit_cards/error/title"}{/s}
		{/capture}
		{assign var="stripeErrorTitle" value=$smarty.capture.stripeErrorTitleCapture}
		{include file="frontend/checkout/stripe_payment_error.tpl"}

		{* Header *}
		<div class="account--welcome panel">
			<h1 class="panel--title">{s name="credit_cards/title"}{/s}</h1>
			<div class="panel--body is--wide">{s name="credit_cards/info"}{/s}</div>
		</div>

		{if $creditCards|@count > 0}
			{* Credit card table *}
			<div class="account--stripe-payment-credit-cards panel is--rounded">
				<div class="panel--table">
					{* Header *}
					<div class="stripe-payment--table-header panel--tr">
						<div class="panel--th column--owner">{s name="credit_cards/table/owner"}{/s}</div>
						<div class="panel--th column--type">{s name="credit_cards/table/type"}{/s}</div>
						<div class="panel--th column--number">{s name="credit_cards/table/number"}{/s}</div>
						<div class="panel--th column--expiry-date">{s name="credit_cards/table/expiry_date"}{/s}</div>
						<div class="panel--th column--actions is--align-center">{s name="credit_cards/table/actions"}{/s}</div>
					</div>

					{* Rows *}
					{foreach name=stripeCreditCards from=$creditCards item=creditCard}
						<div class="stripe-payment--item panel--tr {if $smarty.foreach.stripeCreditCards.last}is--last-row{/if}">
							<div class="panel--td column--owner is--bold">
								<div class="column--label">{s name="credit_cards/table/owner"}{/s}</div>
								<div class="column--value">{$creditCard.name}</div>
							</div>
							<div class="panel--td column--type">
								<div class="column--label">{s name="credit_cards/table/type"}{/s}</div>
								<div class="column--value">{$creditCard.brand}</div>
							</div>
							<div class="panel--td column--number">
								<div class="column--label">{s name="credit_cards/table/number"}{/s}</div>
								<div class="column--value">XXXXXXXXXXXX {$creditCard.last4}</div>
							</div>
							<div class="panel--td column--expiry-date">
								<div class="column--label">{s name="credit_cards/table/expiry_date"}{/s}</div>
								<div class="column--value">{$creditCard.exp_month|string_format:"%02d"}/{$creditCard.exp_year}</div>
							</div>
							<div class="panel--td column--actions">
								<form name="stripeCreditCard-{$creditCard.id}" method="POST" action="{url controller='StripePaymentAccount' action='deleteCreditCard'}">
									<input type="hidden" name="cardId" value="{$creditCard.id}" />
									<button type="submit" class="btn is--primary is--small">{s name="credit_cards/table/actions/delete"}{/s}</button>
								</form>
							</div>
						</div>
					{/foreach}
				</div>
			</div>
		{else}
			{* No saved credit cards *}
			{capture name="stripeNoCardsInfoText"}
				{s name="credit_cards/no_cards"}{/s}
			{/capture}
			{include file="frontend/_includes/messages.tpl" type="info" content=$smarty.capture.stripeNoCardsInfoText}
		{/if}
	</div>
{/block}
