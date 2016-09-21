jQuery(document).ready(function($) {
	if($('.check_open input').is(":checked")){
		$('.check_open input:checked').parent().next('.input_open').show();
	}
	$('.check_open input').click(function() {
		$(this).parent().next('.input_open').slideToggle();
	});


	$('#myOptionsForm legend').click(function(event) {
		
		if(!$(this).hasClass('act')) {
			$('#myOptionsForm legend').removeClass('act');
			$(this).addClass('act');
			$('#myOptionsForm fieldset').slideUp();
			$(this).next('fieldset').slideDown();
		}
	});


	//Sortable
    $("#gil_list").sortable({
        stop: function(event, ui) {
            m3c_update_sortable_indexes();
        }
    });
    $("#gil_list").disableSelection();
	function m3c_update_sortable_indexes() {
	    var positions = new Array();
	    $('#gil_list > label > input:first-child').each(function(i, obj) {
	        positions[i] = $(this).attr('data-href');
	    });
	    var data = {
	        action: 'update_menu_positions',
	        menu_item_positions: positions.toString()
	    };
	    $.post(ajaxurl, data, function(response) {});
	}

 


 	$('#upload_logo_button').click(function() {
        tb_show('Upload a logo', 'media-upload.php?referer=rspanel&type=image&TB_iframe=true&post_id=0', false);
        return false;
    });

  	window.send_to_editor = function(html) {
	 var imgurl = $('img',html).attr('src');
	 if(typeof imgurl=='undefined'){
	 	 var imgurl = $(html).attr('src');
	 }
		console.log(imgurl);
	 $('input[data-id="upload_image"]').val(imgurl);
	 tb_remove();
	}






});