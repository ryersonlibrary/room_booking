<!DOCTYPE html>
<!--[if IE 6]>
<html lang="en-US" id="ie6" class="lt-ie9 lt-ie8 lt-ie7 no-js">
<![endif]-->
<!--[if IE 7]>
<html lang="en-US" id="ie7" class="lt-ie9 lt-ie8 no-js">
<![endif]-->
<!--[if IE 8]>
<html lang="en-US" id="ie8" class="lt-ie9 no-js">
<![endif]-->
<!--[if !(IE 6) | !(IE 7) | !(IE 8)  ]><!-->
<html lang="en-US">
<!--<![endif]-->
<head>
    <title>Room Booking - Ryerson University Library and Archives</title>
    <meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/jquery.mobile-1.4.5.css" type="text/css" media="screen" />
	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/mobile.css" type="text/css" media="screen" />
	<script src="<?php echo base_url(); ?>assets/js/jquery.mobile-1.4.5.min.js"></script>

	
	<!--- It's just easier that way. Thanks Jquery Mobile --->
	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/datepicker/jquery.mobile.datepicker.css" />
	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/datepicker/jquery.mobile.datepicker.theme.css" />
	<link rel="stylesheet" href="<?php echo base_url(); ?>assets/datepicker/theme-template.css" />

<?php if(isset($headers)) echo $headers; ?>

</head>
<body>
	<div data-role="header" >
		<div><img id="logo_left" style="padding: 0.6em;" src="https://library.cf.ryerson.ca/studentbooking/mobile/img/mobile-logo.png" class="" alt="Ryerson Library and Archives" ></div>
		
		
		
	</div><!-- /header -->
	
	<div data-role="content">	
	
	 <?php if(isset($content)) echo $content; ?>
	 
	 </div><!-- /content -->

	 <div class="footer">
		 <a rel="external" href="<?php echo base_url(); ?>booking">Full Site</a> | 
		 <a href="mailto:refdesk@ryerson.ca">refdesk@ryerson.ca</a> | 
		 <a href="tel:416-979-5055">416-979-5055</a>
	 </div>
	 
</body>
</html>
