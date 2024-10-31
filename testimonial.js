	jQuery(document).ready(function($) {
			
			$('#contactForm').validate({
				rules: {
					contactName: {
						required: true,
						
					},
					email: {
						required: true,
						email: true
					},
					
					commentsText: {
						required: true,
					},
				},
				
				messages: {
					contactName: "Name Required",
					email: "Invalid Email",
					commentsText: "Message Required"
				}
			});
		});