/*
 * 	Character Count Plugin - jQuery plugin
 * 	Dynamic character count for text areas and input fields
 *	written by Alen Grakalic	
 *	http://cssglobe.com/post/7161/jquery-plugin-simplest-twitterlike-dynamic-character-count-for-textareas
 *
 *	Copyright (c) 2009 Alen Grakalic (http://cssglobe.com)
 *	Dual licensed under the MIT (MIT-LICENSE.txt)
 *	and GPL (GPL-LICENSE.txt) licenses.
 *
 *	Built for jQuery library
 *	http://jquery.com
 *
 */
 
(function($) {

	 $.fn.metaCount = function(options){
	  
		// default configuration properties
		var defaults = {	
			allowed: 0,		
			warning: 0,
			css: 'counter',
			counterElement: 'span',
			cssWarning: 'warning',
			cssExceeded: 'exceeded',
			cssyellow1: 'yellow1',
			cssgreen: 'green',
			cssred: 'red',
			counterText: ''
		}; 
			
		var options = $.extend(defaults, options); 
		
		function calculate(obj){
			var count = $(obj).val().length;
			var available = options.allowed + count;
            			//alert(available);
            			$(obj).next().removeClass(options.cssyellow1);
            			$(obj).next().removeClass(options.cssgreen);
            			$(obj).next().removeClass(options.cssred);
             if(available < 70){
				$(obj).next().addClass(options.cssyellow1);
			} 
			else if(available > 70 && available <= 140 ){
				$(obj).next().addClass(options.cssgreen);
			} else if(count > 140 ){
				$(obj).next().addClass(options.cssred);
			} 
            
			
			//$(obj).next().html(options.counterText +'<span style="color:#000 !important;">'+available+'</span>')
			$(obj).next().html('<span style="color:#000 !important;font-family: arial;ont-size: 14px;font-weight: bold;padding-right: 10px;padding-top: 5px;">'+options.counterText + '</span>'+available)
		};
				
		this.each(function() {  			
			$(this).after('<'+ options.counterElement +' class="' + options.css + '">'+ options.counterText +'</'+ options.counterElement +'>');
			calculate(this);
			$(this).keyup(function(){calculate(this)});
			$(this).change(function(){calculate(this)});
		});
	  
	};

})(jQuery);
