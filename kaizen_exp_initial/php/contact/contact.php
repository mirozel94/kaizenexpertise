<?php
	require_once 'lib/validation/validation.php';

	global $CONFIG;
	$CONFIG = array(
		/* Mail Options */
		'mail_send_to' =>'contact@kaizenconseil.fr', 
		'mail_contents'=>'mail-content.php', 

		/* Messages */
		'messages'=>array(
			'mail_failed' =>'Une erreur inconnue est apparue pendant que vous envoyiez le message', 
			'form_error'  =>'<strong>Les erreurs suivantes se sont produites</strong><br><ul><li>%s</li></ul>', 
			'form_success'=>'<strong>Merci!</strong><br>Votre message a été envoyé, nous vous répondrons le plus rapidement possible', 
			'form_fields' =>array(
				'name'=>array(
					'required'=>'Le nom est requis'
				), 
				'email'=>array(
					'required'=>"L'E-mail est requis", 
					'email'=>'E-mail invalide'
				), 
				'url'=>array(
					'url'=>'URL invalide'
				), 
				'subject'=>array(
					'required'=>'Le sujet du message est requis'
				), 
				'message'=>array(
					'required'=>'Le message est requis'
				), 
				'honeypot'=>array(
					'invalid'=>'Êtes-vous un humain ?'
				)
			)
		)
	);
	
	function createFormMessage( $formdata )
	{
		global $CONFIG;
		
		ob_start();
		
		extract($formdata);
		include $CONFIG['mail_contents'];
		
		return ob_get_clean();
	}

	function validate_honeypot( $array, $field ) {
		if( '' !== $array[ $field ] ) {
			$array->add_error( $field, 'invalid' );
		}
	}

	function cleanInput($input) {
		$search = array(
			'@<script[^>]*?>.*?</script>@si',   // Strip out javascript
			'@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
			'@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
			'@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
		);

		$output = preg_replace($search, '', $input);
		return $output;
	}

	function sanitize($input) {
		if (is_array($input)) {
			foreach($input as $var=>$val) {
				$output[$var] = sanitize($val);
			}
		}
		else {
			if (get_magic_quotes_gpc()) {
				$input = stripslashes($input);
			}
			$input  = cleanInput($input);
			$output = $input;
		}
		return $output;
	}
	
	$response = array();
	$validator = new Validation( sanitize( $_POST[ 'cf' ] ) );
	$validator
		->pre_filter('trim')
		->add_rules('name', 'required')
		->add_rules('email', 'required', 'email')
		->add_rules('url', 'url')
		->add_rules('subject', 'required')
		->add_rules('message', 'required')
		->add_callbacks('honeypot', 'validate_honeypot');
	
	if( $validator->validate() )
	{
		require_once( 'lib/swiftmail/swift_required.php' );
		
		$transport = Swift_MailTransport::newInstance();
		$mailer = Swift_Mailer::newInstance($transport);
		
		$formdata = $validator->as_array();
		$body = createFormMessage($formdata);
		
		$message = Swift_Message::newInstance();
		$message
			->setSubject($formdata['subject'])
			->setFrom($formdata['email'])
			->setTo($CONFIG['mail_send_to'])
			->setBody($body, 'text/html');
			
		if( !$mailer->send($message) ) {
			$response['success'] = false;
			$response['message'] = $CONFIG['messages']['mail_failed'];
		} else {
			$response['success'] = true;
			$response['message'] = $CONFIG['messages']['form_success'];
		}
	} else {
		$response = array(
			'success'=>false, 
			'message'=>sprintf($CONFIG['messages']['form_error'], implode('</li><li>', $validator->errors($CONFIG['messages']['form_fields']) ) )
		);
	}
	
	echo json_encode($response);

	exit();