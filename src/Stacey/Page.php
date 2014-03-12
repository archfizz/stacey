<?php

namespace Stacey;

class Page
{
  public $url_path;
  public $file_path;
  public $template_name;
  public $template_file;
  public $template_type;
  public $data;
  public $all_pages;

  public function __construct($url, $content = false)
  {
    # store url and converted file path
    $this->file_path = Helpers::url_to_file_path($url);
    $this->url_path = $url;

    $this->template_name = self::template_name($this->file_path);
    $this->template_file = self::template_file($this->template_name);
    $this->template_type = self::template_type($this->template_file);
    # create/set all content variables
    PageData::create($this, $content);
  }

  public function clean_json($data)
  {
    # strip any trailing commas
    # (run it twice to get partial matches)
    $data = preg_replace('/([}\]"][\s\n]*),([\s\n]*[}\]])/', '$1$2', $data);
    $data = preg_replace('/([}\]"][\s\n]*),([\s\n]*[}\]])/', '$1$2', $data);
    # strip newline characters
    $data = preg_replace('/\n/', '', $data);
    # minfy it
    $data = JSMin::minify($data);

    return $data;
  }

  public function parse_template()
  {
    $data = TemplateParser::parse($this->data, $this->template_file);
    # post-parse JSON
    if (strtolower($this->template_type) == 'json') {
      $data = $this->clean_json($data);
    }

    return $data;
  }

  # magic variable assignment
  public function __set($name, $value)
  {
    $this->data[strtolower($name)] = $value;
  }

  public static function template_type($template_file)
  {
    preg_match('/\.([\w\d]+?)$/', $template_file, $ext);

    return isset($ext[1]) ? $ext[1] : false;
  }

  public static function template_name($file_path)
  {
    $txts = array_keys(Helpers::list_files($file_path, '/\.(yml|txt)/'));
    # return first matched .yml file

    return (!empty($txts)) ? preg_replace('/\.(yml|txt)/', '', $txts[0]) : false;
  }

  public static function template_file($template_name)
  {
    preg_match('/(\.[\w\d]+?)$/', $_SERVER["REQUEST_URI"], $ext);
    $extension = isset($ext[1]) ? $ext[1] : '.*';
    $template_name = preg_replace('/([^.]*\.)?([^.]*)$/', '\\2', $template_name);
    $template_file = glob(Config::$templates_folder.'/'.$template_name.$extension);
    if (!isset($template_file[0])) $template_file = glob(Config::$templates_folder.'/'.$template_name.'.*');
    # return template if one exists

    return isset($template_file[0]) ? $template_file[0] : Config::$templates_folder.'/default.html';
  }

}
