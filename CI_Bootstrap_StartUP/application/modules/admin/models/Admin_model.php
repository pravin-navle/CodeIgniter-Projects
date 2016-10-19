<?php

class Admin_model extends CI_Model
{

    private $def_lang;

    public function __construct()
    {
        parent::__construct();
        $this->def_lang = $this->config->item('language_abbr');
    }

    public function loginCheck($values)
    {
        $arr = array(
            'username' => $values['username'],
            'password' => md5($values['password']),
        );
        $this->db->where($arr);
        $result = $this->db->get('users');
        $res_arr = $result->row_array();
        return $res_arr;
    }

    public function articlesCount($search = null)
    {
        if ($search !== null) {
            $search = $this->db->escape_like_str($search);
            $this->db->where("(translations.title LIKE '%$search%' OR translations.description LIKE '%$search%')");
        }
        $this->db->join('translations', 'translations.for_id = articles.id', 'left');
        $this->db->where('translations.type', 'article');
        $this->db->where('translations.abbr', $this->def_lang);
        return $this->db->count_all_results('articles');
    }

    public function getLanguages()
    {
        $query = $this->db->query('SELECT * FROM languages');
        return $query;
    }

    public function getAdminUsers($id = null)
    {
        if ($id != null) {
            $this->db->where('id', $id);
        }
        $query = $this->db->query('SELECT * FROM users');
        if ($id != null)
            return $query->row_array();
        else
            return $query;
    }

    public function countLangs($name = null, $abbr = null)
    {
        if ($name != null)
            $this->db->where('name', $name);
        if ($abbr != null)
            $this->db->or_where('abbr', $abbr);
        return $this->db->count_all_results('languages');
    }

    public function setAdminUser($post)
    {
        $post['password'] = md5($post['password']);
        if ($post['edit'] > 0) {
            if (strlen(trim($post['password'])) < 3) {
                unset($post['password']);
            }
            $this->db->where('id', $post['edit']);
            unset($post['id'], $post['edit']);
            $result = $this->db->update('users', $post);
        } else {
            unset($post['edit']);
            $result = $this->db->insert('users', $post);
        }
        return $result;
    }

    public function setLanguage($post)
    {
        $result = $this->db->insert('languages', $post);
        return $result;
    }

    public function deleteLanguage($id)
    {
        $this->db->where('id', $id);
        $result = $this->db->delete('languages');
        return $result;
    }

    public function setArticle($post, $id = 0)
    {
        if ($id > 0) {
            unset($post['title_for_url']);
            $post['time_update'] = time();
            $result = $this->db->where('id', $id)->update('articles', $post);
        } else {
            if (trim($post['title_for_url']) != '') {
                $url_fr = except_letters($post['title_for_url']);
            } else {
                $url_fr = 'article';
            }
            unset($post['title_for_url']);
            $this->db->select_max('article_id');
            $query = $this->db->get('articles');
            $rr = $query->row_array();
            $post['article_id'] = $rr['article_id'] + 1;
            $post['url'] = str_replace(' ', '_', $url_fr . '_' . $post['article_id']);
            $post['time'] = time();
            $result = $this->db->insert('articles', $post);
            $last_id = $this->db->insert_id();
        }
        if ($result == false)
            return false;
        else {
            if ($id > 0) {
                return $id;
            } else {
                return $last_id;
            }
        }
    }

    public function setArticleTranslation($post, $id, $is_update)
    {
        $i = 0;
        $current_trans = $this->getTranslations($id, 'article');
        foreach ($post['abbr'] as $abbr) {
            $arr = array();
            $emergency_insert = false;
            if (!isset($current_trans[$abbr])) {
                $emergency_insert = true;
            }
            $post['title'][$i] = str_replace('"', "'", $post['title'][$i]);
            $arr = array(
                'title' => $post['title'][$i],
                'basic_description' => $post['basic_description'][$i],
                'description' => $post['description'][$i],
                'abbr' => $abbr,
                'for_id' => $id,
                'type' => 'article'
            );
            if ($is_update === true && $emergency_insert === false) {
                $abbr = $arr['abbr'];
                unset($arr['for_id'], $arr['abbr'], $arr['url']);
                $this->db->where('abbr', $abbr)->where('for_id', $id)->where('type', 'article')->update('translations', $arr);
            } else
                $this->db->insert('translations', $arr);
            $i++;
        }
    }

    public function getTranslations($id, $type)
    {
        $this->db->where('for_id', $id);
        $this->db->where('type', $type);
        $query = $this->db->select('*')->get('translations');
        $arr = array();
        foreach ($query->result() as $row) {
            $arr[$row->abbr]['title'] = $row->title;
            $arr[$row->abbr]['basic_description'] = $row->basic_description;
            $arr[$row->abbr]['description'] = $row->description;
        }
        return $arr;
    }

    public function historyCount()
    {
        return $this->db->count_all_results('history');
    }

    public function setHistory($activity, $user)
    {
        $this->db->insert('history', array('activity' => $activity, 'username' => $user, 'time' => time()));
    }

    public function getHistory($limit, $page)
    {
        $this->db->order_by('id', 'desc');
        $query = $this->db->select('*')->get('history', $limit, $page);
        return $query;
    }

    public function getArticles($limit, $page, $search = null, $orderby = null)
    {
        if ($search !== null) {
            $search = $this->db->escape_like_str($search);
            $this->db->where("(translations.title LIKE '%$search%' OR translations.description LIKE '%$search%')");
        }
        if ($orderby !== null) {
            $this->db->order_by('articles.id', $orderby);
        } else {
            $this->db->order_by('articles.id', 'desc');
        }
        $this->db->join('translations', 'translations.for_id = articles.id', 'left');
        $this->db->where('translations.type', 'article');
        $this->db->where('translations.abbr', $this->def_lang);
        $query = $this->db->select('articles.*, translations.title, translations.description, translations.abbr, articles.url, translations.for_id, translations.type, translations.basic_description')->get('articles', $limit, $page);
        return $query;
    }

    public function getCategories($lang = null)
    {
        if ($lang != null) {
            $where = " AND language = '$lang'";
        }
        $query = $this->db->query('SELECT categories.*, (SELECT COUNT(id) FROM articles WHERE articles.category = name) as num FROM `categories` ORDER BY `id` DESC ');
        return $query;
    }

    public function getOneArticle($id)
    {
        $query = $this->db->where('id', $id)
                ->get('articles');
        if ($query->num_rows() > 0) {
            return $query->row_array();
        } else {
            return false;
        }
    }

    public function setCategorie($post)
    {
        $id = $post['id'];
        unset($post['id']);
        if ($id == 0) {
            $result = $this->db->insert('categories', $post);
        } else {
            if (isset($post['rename_all'])) {
                $this->db->where('category', $post['rename_all']);
                unset($post['rename_all']);
                $this->db->update('articles', array('category' => $post['name']));
            }
            $this->db->where('id', $id);
            $result = $this->db->update('categories', $post);
        }
        return $result;
    }

    public function deleteCategorie($id)
    {
        $this->db->where('id', $id);
        $result = $this->db->delete('categories');
        return $result;
    }

    public function deleteArticle($id)
    {
        $this->deleteTranslations($id, 'article');
        $this->db->where('id', $id);
        $result = $this->db->delete('articles');
        return $result;
    }

    private function deleteTranslations($id, $type)
    {
        $this->db->where('for_id', $id);
        $this->db->where('type', $type);
        $this->db->delete('translations');
    }

    public function articleStatusChagne($id, $to_status)
    {
        $this->db->where('id', $id);
        $result = $this->db->update('articles', array('visibility' => $to_status));
        return $result;
    }

    public function changePass($new_pass, $username)
    {
        $this->db->where('username', $username);
        $result = $this->db->update('users', array('password' => md5($new_pass)));
        return $result;
    }

}
