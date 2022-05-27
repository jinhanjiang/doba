<?php
use Doba\Util;

class PaginationPlugin extends BasePlugin {
    
    // The page tag is used to control the url page. For example, pagenum in xxx.php?pagenum=2
    public $pageName = 'nowpage';

    // Number of records per page
    public $perPage = 10;

    // total
    public $total = 0;

    // total pages
    public $totalPage = 0;

    // current page
    public $currentPage = 0;

    // Control the number of records
    public $pageBarNum = 10;

    // URL
    public $linkurl = "";

    // Previous page
    public $prePage = "&lt;";
    public $preBar = "&lt;&lt;";

    // Next page
    public $nextPage = "&gt;";
    public $nextBar = "&gt;&gt";

    // Home
    public $firstPage = "Home";

    // last page
    public $lastPage = "Last";

    // Selected page style
    public $selectPageCss = "active";

    // Js pagination method on the page
    public $getPaginationData = '';

    // Other conditions
    public $additional = '';

    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    /**
     * Initial page
     */
    public function start($params = array()) {
        // Customize current page request field
        if($params['pageName']) $this->pageName = $params['pageName'];
        if(isset($_GET[$this->pageName]) && $_GET[$this->pageName] > 0){
            $this->currentPage = intval($_GET[$this->pageName]);
        } else if(isset($_POST[$this->pageName]) && $_POST[$this->pageName] > 0){
            $this->currentPage = intval($_POST[$this->pageName]);
        } else if(isset($params['currentPage']) && $params['currentPage'] > 0){
            $this->currentPage = intval($params['currentPage']);
        } else {
            $this->currentPage = 1;    
        }
        // Ajax method called on the page
        if($params['callFunction']) {
            $this->getPaginationData = $params['callFunction'];
        }

        // Other paramenters passed in the method in the click event
        if(! empty($params['additionalFields'])) {
            $additionalFields = empty($params['additionalFields']) 
                ? [] : explode(',', $params['additionalFields']);
            $additional = [];
            if(is_array($additionalFields)) {
                foreach($additionalFields as $field) {
                    $additional[] = addslashes($_POST[$field]) ?? '';
                }
            }
            $this->additional = empty($additional) ? '' : ",'".implode("','", $additional)."'";
        }
        else if(! empty($params['additional'])) {
            $additional = [];
            if(is_array($params['additional']) && count($params['additional']) > 0) {
                foreach($params['additional'] as $additionalVal) {
                    $additional[] = addslashes($additionalVal);
                }
            }
            $this->additional = empty($additional) ? '' : ",'".implode("','", $additional)."'";
        }
        return $this;
    }

    /**
     * Get the value required by limit in the mysql statement
     *
     * @return string
     */
    public function limit()
    {
        if(empty($this->currentPage)) $this->start();
        else if(($this->totalPage >= 1) && $this->currentPage > $this->totalPage) {
            $this->currentPage = $this->totalPage ;    
        }
        return (($this->currentPage - 1) * $this->perPage).",".$this->perPage;
    }

    /**
     * Set the total number of records
     */
    public function setPageTotal($total=0)
    {   
        $this->total = (int)$total;
        $this->totalPage = ceil($this->total / $this->perPage);     
    }

    /**
     * Generate page breaks
     */
    public function bar() {
        return ($this->total > 0 && 
                (empty($_REQUEST[$this->pageName]) ||//$_REQUEST['page_num'] is not set during initialization
                    ($this->currentPage > 0 && $_REQUEST[$this->pageName] == $this->currentPage)//When you click paging, you can judge whether it is out of range here. if it is out of range, it will not be displayed
                )
            ) 
            ? $this->prebar().$this->prepage().$this->nowbar().$this->nextpage().$this->nextbar() 
            : '';
    }

    /**
     * Get the link address
     */
    private function getLinkUrl() {
        $this->start();
        if(empty($_SERVER['QUERY_STRING'])) $this->linkurl = $_SERVER['REQUEST_URI']."?".$this->pageName."=";
        else{
            if(isset($_GET[$this->pageName])) {                
                $this->linkurl = str_replace($this->pageName.'='.$this->currentPage, $this->pageName.'=', $_SERVER['REQUEST_URI']);
            } else {
                $this->linkurl = $_SERVER['REQUEST_URI'].'&'.$this->pageName.'=';
            }
        }
    }

    /**
     * Returns the address value for the specified page
     */
    private function geturl($pageno = 1)
    {
        if($this->getPaginationData) {
            return 'javascript:'.$this->getPaginationData.'('.$pageno.$this->additional.')';
        } else {
            if(empty($this->linkurl))$this->getLinkUrl();
            return str_replace($this->pageName.'=', $this->pageName.'='.$pageno, $this->linkurl);
        }
    }

    /**
     * previous page
     */
    private function prepage() {
        if($this->currentPage > 1) {
            return '<li><a href="'.$this->geturl($this->currentPage - 1).'">'.$this->prePage.'</a></li>';
        }
        return '';
    }

    /**
     * next page
     */
    private function nextpage() {
        if($this->currentPage < $this->totalPage) {
            return '<li><a href="'.$this->geturl($this->currentPage+1).'">'.$this->nextPage.'</a></li>';
        }
        return '';
    }

    /**
     * Limit after too many pages
     */
    private function prebar() {
        if($this->currentPage > ceil($this->pageBarNum / 2)) {
            $pageno = $this->currentPage - $this->pageBarNum;
            if($pageno <= 0) $pageno = 1;
            return '<li><a href="'.$this->geturl($pageno).'">'.$this->preBar."</a></li>";
        }
        return '<li><a href="'.$this->geturl(1).'">'.$this->preBar."</a></li>";
    }

    /**
     * Limit after too many pages
     */
    private function nextbar() {
        if($this->currentPage < $this->totalPage - ceil($this->pageBarNum / 2)){
            $pageno = $this->currentPage + $this->pageBarNum;
            if($pageno > $this->totalPage) $pageno = $this->totalPage;
            return '<li><a href="'.$this->geturl($pageno).'">'.$this->nextBar."</a></li>";
        }
        return '<li><a href="'.$this->geturl($this->totalPage).'">'.$this->nextBar."</a></li>";
    }

    /**
     * Current page bar
     */ 
    private function nowbar() {
        $begin = $this->currentPage - ceil($this->pageBarNum / 2);
        $begin = ($begin >= 1) ? $begin : 1;
        $return = '';
        for($i = $begin; $i < $begin + $this->pageBarNum; $i++)
        {
            if($i <= $this->totalPage) {
                if($i != $this->currentPage)
                    $return .= '<li><a href="'.$this->geturl($i).'">'.$i.'</a></li>';
                else 
                    $return .= '<li class="disabled"><span class="'.$this->selectPageCss.'">'.$i.'</span></li>';
            } else {
                break;
            }
        }
        unset($begin);
        return $return;
    }

}