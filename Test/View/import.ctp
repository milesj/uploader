<?php
echo $this->Form->create('Upload', array('type' => 'file', 'url' => array('controller' => 'upload', 'action' => $this->action)));
echo $this->Form->input('import', array('type' => 'hidden', 'value' => 'true'));
echo $this->Form->end('Import');