<?php
/* For licensing terms, see /license.txt */

require_once 'promotion.lib.php';

/**
 * Class Career
 *
 *	This class provides methods for the notebook management.
 *	Include/require it in your code to use its features.
 *	@package chamilo.library
 */
class Career extends Model
{
    const CAREER_STATUS_ACTIVE = 1;
    const CAREER_STATUS_INACTIVE = 0;
    public $table;
    public $columns = array('id', 'name','description','status','created_at','updated_at');

	public function __construct()
    {
        $this->table =  Database::get_main_table(TABLE_CAREER);
	}

    /**
     * Get the count of elements
     */
    public function get_count()
    {
        $row = Database::select('count(*) as count', $this->table, array(),'first');
        return $row['count'];
    }

    /**
     * @param array $where_conditions
     * @return array
     */
    public function get_all($where_conditions = array())
    {
        return Database::select('*',$this->table, array('where'=>$where_conditions,'order' =>'name ASC'));
    }

    /**
     * Update all promotion status by career
     * @param   int     career id
     * @param   int     status (1 or 0)
    */
    public function update_all_promotion_status_by_career_id($career_id, $status)
    {
        $promotion = new Promotion();
        $promotion_list = $promotion->get_all_promotions_by_career_id($career_id);
        if (!empty($promotion_list)) {
            foreach($promotion_list  as $item) {
                $params['id']     = $item['id'];
                $params['status'] = $status;
                $promotion->update($params);
                $promotion->update_all_sessions_status_by_promotion_id($params['id'], $status);
            }
        }
    }

    /**
     * Displays the title + grid
     */
	public function display()
    {
		echo '<div class="actions" style="margin-bottom:20px">';
        echo '<a href="career_dashboard.php">'.Display::return_icon('back.png',get_lang('Back'),'','32').'</a>';
		echo '<a href="'.api_get_self().'?action=add">'.Display::return_icon('new_career.png',get_lang('Add'),'','32').'</a>';
		echo '</div>';
        echo Display::grid_html('careers');
	}

    /**
     * @return array
     */
    public function get_status_list()
    {
        return array(self::CAREER_STATUS_ACTIVE => get_lang('Unarchived'), self::CAREER_STATUS_INACTIVE => get_lang('Archived'));
    }

    /**
     * Returns a Form validator Obj
     * @param   string  url
     * @param   string  action add, edit
     * @return  obj     form validator obj
     */
    public function return_form($url, $action)
    {
        $form = new FormValidator('career', 'post', $url);
        // Setting the form elements
        $header = get_lang('Add');
        if ($action == 'edit') {
            $header = get_lang('Modify');
        }

        $form->addElement('header', $header);
        $id = isset($_GET['id']) ? intval($_GET['id']) : '';
        $form->addElement('hidden', 'id', $id);

        $form->addElement('text', 'name', get_lang('Name'), array('size' => '70'));
        $form->add_html_editor('description', get_lang('Description'), false, false, array('ToolbarSet' => 'careers','Width' => '100%', 'Height' => '250'));
	    $status_list = $this->get_status_list();
        $form->addElement('select', 'status', get_lang('Status'), $status_list);
        if ($action == 'edit') {
            $form->addElement('text', 'created_at', get_lang('CreatedAt'));
            $form->freeze('created_at');
        }

        if ($action == 'edit') {
        	$form->addElement('style_submit_button', 'submit', get_lang('Modify'), 'class="save"');
        } else {
        	$form->addElement('style_submit_button', 'submit', get_lang('Add'), 'class="save"');
        }

        // Setting the defaults
        $defaults = $this->get($id);

        if (!empty($defaults['created_at'])) {
        	$defaults['created_at'] = api_convert_and_format_date($defaults['created_at']);
        }
        if (!empty($defaults['updated_at'])) {
        	$defaults['updated_at'] = api_convert_and_format_date($defaults['updated_at']);
        }
        $form->setDefaults($defaults);

        // Setting the rules
        $form->addRule('name', get_lang('ThisFieldIsRequired'), 'required');
		return $form;
    }

    /**
     * Copies the career to a new one
     * @param   integer     Career ID
     * @param   boolean     Whether or not to copy the promotions inside
     * @return  integer     New career ID on success, false on failure
     */
    public function copy($id, $copy_promotions = false)
    {
        $career = $this->get($id);
        $new = array();
        foreach ($career as $key => $val) {
            switch ($key) {
                case 'id':
                case 'updated_at':
                    break;
                case 'name':
                    $val .= ' '.get_lang('CopyLabelSuffix');
                    $new[$key] = $val;
                    break;
                case 'created_at':
                    $val = api_get_utc_datetime();
                    $new[$key] = $val;
                    break;
                default:
                    $new[$key] = $val;
                    break;
            }
        }
        $cid = $this->save($new);
        if ($copy_promotions) {
            //Now also copy each session of the promotion as a new session and register it inside the promotion
            $promotion = new Promotion();
            $promo_list   = $promotion->get_all_promotions_by_career_id($id);
            if (!empty($promo_list)) {
                foreach($promo_list  as $item) {
                    $pid = $promotion->copy($item['id'], $cid);
                }
            }
        }
        return $cid;
    }

    /**
     * @param int $career_id
     * @return bool
     */
    public function get_status($career_id)
    {
        $TBL_CAREER             = Database::get_main_table(TABLE_CAREER);
        $career_id = intval($career_id);
        $sql 	= "SELECT status FROM $TBL_CAREER WHERE id = '$career_id'";
        $result = Database::query($sql);
        if (Database::num_rows($result) > 0) {
            $data = Database::fetch_array($result);
            return $data['status'];
        } else {
            return false;
        }
    }


    public function save($params, $show_query = false) {
	    $id = parent::save($params, $show_query);
	    if (!empty($id)) {
	    	Event::addEvent(LOG_CAREER_CREATE, LOG_CAREER_ID, $id, api_get_utc_datetime(), api_get_user_id());
   		}
   		return $id;
    }

     /**
     * @param int $id
     * @return bool|void
     */
    public function delete($id) 
    {
	    parent::delete($id);
	    Event::addEvent(LOG_CAREER_DELETE, LOG_CAREER_ID, $id, api_get_utc_datetime(), api_get_user_id());
    }
}
