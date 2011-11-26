<?php 
// Download: http://valums.com/ajax-upload/
$this->Html->css('fileuploader.css', array('inline' => false));
$this->Html->script('fileuploader.js', array('inline' => false)); ?>

<div id="file-uploader">       
    <noscript>          
        <p>Please enable JavaScript to use file uploader.</p>
    </noscript>         
</div>
			
<script type="text/javascript">
	var uploader = new qq.FileUploader({
		element: document.getElementById('file-uploader'),
		action: '/upload/ajax_upload'
	});
</script>