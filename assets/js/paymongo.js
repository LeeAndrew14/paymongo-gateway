jQuery(document).ready(($) => {	
	const data = paymongo_params;
	console.log(data);
	const checkout_form = $('form.woocommerce-checkout');

	$('form.woocommerce-checkout').on('submit', (e) => {
		if (data.url !== null) {
			e.preventDefault();

			const paymongo = window.open(data.html_file, '_blank', 'width=500, height=500');

			paymongo.paymongo_data = data.url;

			console.log(wc_checkout_params);

			const receiveMessage = (event) => {
				if (event.data === "3DS-authentication-complete") {
					const payment_url = `https://api.paymongo.com/v1/payment_intents/${data.payment_intent_id}?client_key=${data.client_key}`;

					fetch(payment_url, {
						mode: 'cors',
						method: 'GET',
						headers: {
							Authorization: `Basic ${window.btoa(data.key)}`
						}
					}).then((response) => {
						if (!response.ok) {
							throw new Error('Connection error');
						}

						return response.json();
					}).then((pi_data) => {
						console.log(pi_data);
						const payment_intent_status = pi_data.data.attributes.status;

						if (payment_intent_status === 'succeeded') {
							$.ajax({
								type: 'post',
								dataType: 'json',
								url: data.ajax_url,
								data: {
									action: 'finalize_payment_process_2',
        							_ajax_nonce: data.nonce,
								},								
								success: (response) => {
								  console.log('Successful! My post data is: ' + JSON.stringify(response));
								},
								error: (error) => {
								  console.error('error' + JSON.stringify(error));
								}
							});
						} else if (payment_intent_status === 'awaiting_payment_method') {
							let error_message = pi_data.data.attributes.last_payment_error;

							if (error_message !== null) {
								error_message = pi_data.data.attributes.last_payment_error.failed_message;
							} else {
								error_message = 'Something went wrong please try again.';
							}

							console.log(error_message);
						}
					});
				}
			}

			const timer = setInterval(() => {
				paymongo.addEventListener('message', receiveMessage, false);
				if (paymongo.closed) {
					clearInterval(timer);
				}
			}, 500);
		}
	});	
});

function setThreeDSecureURL() {
	document.getElementById('paymongo_iframe').src = window.paymongo_data;		
}