<script type="text/javascript" >
 $(document).ready(function() { 
		
            $('#photoimg').live('change', function()			{ 
			           $("#preview").html('');
			    $("#preview").html('<img src="../wp-content/plugins/seo-title-tag/loader.gif" alt="Uploading...."/>');
			$("#imageform").ajaxForm({
						target: '#preview'
		}).submit();
		
			});
        }); 
</script>

<style>

body
{
font-family:arial;
}
.preview
{
width:200px;
border:solid 1px #dedede;
padding:10px;
}
#preview
{
color:#cc0000;
font-size:12px
}

</style>
    <h3>"Upload Titles and Meta Descriptions directly through a CSV File":</h3>
<tr>
		 <th scope="row">Select a CSV file to upload:</th>
		 
		 <div class="">
<form id="imageform" method="post" enctype="multipart/form-data" action='../wp-content/plugins/seo-title-tag/ajaximage.php'>
<input type="file" name="photoimg" id="photoimg" />
</form>
<div id='preview'>
	
</div>
</div>
<td>
</td>
