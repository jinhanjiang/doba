<?php
use Doba\Util;
use Doba\Dao\XxxDAO;

class BlogController extends BaseController
{
    public function __construct() {
        parent::__construct();
        // Add the following statement for permission verification

        // if(! $this->memberInfo) forward('login');
    }

    public function pageList() {
        $data['default'] = langi18n('Current content, under the blog directory of the language pack');

        $this->assign($data);
    }

    public function ajaxResponse() {

        if(! $_POST['test']) {
            throw new Exception(langi18n('It\'s a big bug'));
        }

        $this->json(array('data'=>'OK'));
    }
}