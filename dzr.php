<?
class dzr {
	public $session;
	public $lastCashe='';
	public $param=array();
	public $data;
	public $city;
	public $lastLoadTime=0;
	/**
	* params must have:
	* @dzr_login - логин команды от движка
	* @dzr_pass - пин команды от движка
	* @dzr_user - array(
	** (string)cookie_last - serialize cookie
	** (string)login - логин игрока
	** (string)pass - пароль игрока
	** [(function)saveCookie] - функция сохранения кук пользователя ($login, $newCookie)
	* )
	* [saveallpages]
	*/
	function __construct($param) {
		$this->param=$param;
		$login=$this->param['dzr_login'];
		if(strpos($login,'_')!==false) {
			$city=explode('_',$this->param['dzr_login']);
			$this->city=$city[0];
		}
	}
	static function cp2utf($mix){
		if(is_array($mix)) return array_map('dzr::cp2utf', $mix);
		if(is_string($mix) && !is_numeric($mix)) return iconv("cp1251", "UTF-8", $mix);
		return $mix;
	}
	public function load($url) {
		if(!empty($this->city)) {
			$url=str_replace('{city}', $this->city, $url);
		}
		if(empty($this->session)) {
			$option=array(
				'auth'=>array($this->param['dzr_login'],$this->param['dzr_pass']),
				'redirects'=>3,
				'redirect_like_browser'=>true,
				);
			if(is_array($this->param['dzr_user']) && strlen($this->param['dzr_user']['cookie_last'])>10) {
				$option['cookies']=unserialize($this->param->['dzr_user']['cookie_last']);
			}
			$this->session = new Requests_Session($url,null,null,$option);
		}
		if(time()-$this->lastLoadTime<1) {
			sleep(1);
		}
		$data=$this->session->get($url);
		if(strpos(dzr::cp2utf($data->body),'Авторизуйтесь.')) {
			// echo 'нет авторизации';
			sleep(1);
			if(is_array($this->param['dzr_user']) && strlen($this->param['dzr_user']['login'])>0 && strlen($this->param['dzr_user']['pass'])>0) {
				$post=array('notags'=>'on','action'=>'auth','login'=>(string)$this->param['dzr_user']['login'],'password'=>(string)$this->param['dzr_user']['pass']);
				$data=$this->session->post($url,null,$post);
			}
		}
		$lastLoadTime=time();
		if(serialize($data->cookies->get())!==$this->param['dzr_user']['cookie_last'] && is_callable($this->param['dzr_user']['saveCookie'])) {
			$this->param['dzr_user']['saveCookie']($this->param['dzr_user']['login'],serialize($data->cookies->get()));
		}
		$this->lastCashe=$data;
		if(isset($this->param['saveallpages']) && $this->param['saveallpages']) $this->saveToLogs($this->param['dzr_login'].':'.$this->param['dzr_pass'].' > '.$url);
		return $data;

	}
	public function sendcode($url,$code,$second=false) {
		if(!empty($this->city)) {
			$url=str_replace('{city}', $this->city, $url);
		} else {
			$city=explode('_',$this->param['dzr_login']);
			$this->city=$city[0];
			$url=str_replace('{city}', $this->city, $url);
		}
		if(empty($session)) {
			$option=array(
				'auth'=>array($this->param['dzr_login'],$this->param['dzr_pass']),
				'redirects'=>3,
				'redirect_like_browser'=>true,
				);
			if($this->param['dzr_user_id']>0 && strlen($this->param->dzr_user['cookie_last'])>10) {
				$option['cookies']=unserialize($this->param->dzr_user['cookie_last']);
			}
			$this->session = new Requests_Session($url,null,null,$option);
		}
		if(time()-$this->lastLoadTime<1) {
			sleep(1);
		}
		$data=$this->session->post($url,null,array(
			'log'=>'',
			'mes'=>'',
			'legend'=>'',
			'nostat'=>'',
			'notext'=>'',
			'refresh'=>'15',
			'bonus'=>'',
			'kladMap'=>'',
			'notags'=>'',
			'cod'=>$code,
			'action'=>'entcod',
			));
		if(strpos(dzr::cp2utf($data->body),'Авторизуйтесь.')) {
			echo 'нет авторизации';
			sleep(1);
			if($this->param['dzr_user_id']>0 && strlen($this->param->dzr_user['login'])>0 && !$second) {
				$post=array('notags'=>'on','action'=>'auth','login'=>(string)$this->param->dzr_user['login'],'password'=>(string)$this->param->dzr_user['pass']);
				$data=$this->session->post($url,null,$post);
				$data=$this->sendcode($url,$code,1);
			}elseif($second) {
				// ошибка авторизации
				return false;
			}
		}
		$lastLoadTime=time();
		if(serialize($data->cookies->get())!==$this->param->dzr_user['cookie_last']) {
			$this->param->dzr_user->update(array('cookie_last'=>serialize($data->cookies->get())));
		}
		$this->lastCashe=$data;
		if(isset($this->param['saveallpages']) && $this->param['saveallpages']) $this->saveToLogs($this->param['dzr_login'].':'.$this->param['dzr_pass'].' > '.$url);
		return $data;

	}
	function saveToLogs($pre) {
		file_put_contents($this->param['saveallpages'].'/'.time().'.html',$pre."\n".$this->lastCashe->body);
		
	}
	function parse($html) {
		$doc=phpQuery::newDocumentHTML(cp2utf($html));
		$tasks=array();
		$cTask=0;
		foreach ($doc['td[width="70%"] > *'] as $key => $value) {
			$tasks[$cTask][]=pq($value);
			// $tasksT[$cTask][]=(string)pq($value);
			if($value->tagName=='form') {
				$cTask++;
			}
		}
		if(preg_match('#countDown\((\d+)\)#', $html,$m)){
			$this->data['timerEnd']=$m[1]+time();
		}
		if(preg_match('/mrid#([^#]+)#/', $html,$m)){
			$this->data['mrid']=$m[1];
		}
		foreach ($doc['div.mini > span.team'] as $key => $value) {
			$this->data['team1'][$key]=(string)pq($value);
		}
		$this->data['team']=trim(strip_tags($this->data['team1'][0]));
		$this->data['user']=trim(strip_tags($this->data['team1'][1]));
		unset($this->data['team1']);
		// highlight_string(print_r($tasksT,true));
		$this->convert_tasks($tasks);
		// file_put_contents('last/'.(string)$this->param['dzr_login'].'.serlz',serialize($this->data));
		// <div class=sysmsg style='text-align:center'><B>Код не принят.</B><br>Если вы уверены в правильности вводимого кода, проверьте, не выдано ли вам следующее задание. Может быть кто-то из вашей команды уже ввел этот код и вы пытаетесь отправить его повторно к пройденному уже уровню.</div>

	}
	function saveaslast() {
		file_put_contents('last/'.(string)$this->param['dzr_login'].'.serlz',serialize($this->data));
	}
	function convert_tasks($tasks) {
		foreach ($tasks as $key => $value) {
			$task=$this->convert_one_task($value);
			$x=0;
			if(!isset($task['level'])) {
				$task['level']='unknow_'.$x;
				++$x;
			}
			$this->data['tasks'][$task['level']]=$task;
		}
	}
	function checkDiff($new,$old) {
		if(!isset($new['tasks']['main'])) {
			return false;
		}
		if($new['tasks']['main']['task_number']!=$old['tasks']['main']['task_number']) {
			if(isset($new['tasks']['main']['task_number'])) {
				return "Выдано новое задание ". $new['tasks']['main']['task_number'];
			} else {
				return "Новое задание вроде не запланировано пока.";
			}
		}

		if($new['tasks']['main']['spoilerExist'] && $new['tasks']['main']['spoilerOpen']!=$old['tasks']['main']['spoilerOpen'] && $new['tasks']['main']['spoilerOpen']) {

			//spoilerExist spoilerOpen

			return "Открыт спойлер";
		}
		$out=array();
		foreach ($new['tasks']['main']['codes'] as $k => $v) {
			if(is_array($v)) {
				$codeOut=array();
				$codeOut['undone']=0;
				foreach ($v as $index => $code) {

					if($code['need']!==$old['tasks']['main']['codes'][$k][$index]['need'] && $code['need']===false) {
						$codeOut['done'][]='*'.($index).')* '.$code['ko'];
					}
					if($code['need']===true) {
						$codeOut['undone']++;
					}
				}

				if(isset($codeOut['done'])) {
					$out[]='Взяты ' . $last. '';
					$out[]=implode(', ',$codeOut['done']);//.($codeOut['undone']>0?' / осталось '.$codeOut['undone'].'':' / Все взяты');
				}
			} else {
				$last=$v;
			}
		}
		if(isset($new['tasks']['main']['p1']) && !isset($old['tasks']['main']['p1'])) {
			$out[]='Получена первая подсказка';
		}
		if(isset($new['tasks']['main']['p2']) && !isset($old['tasks']['main']['p2'])) {
			$out[]='Получена вторая подсказка';
		}
		if(count($out)>0) {
			return implode("\n", $out);
		} else {
			return false;
		}
	}
	function convert_one_task($task) {
		$ta=array('html'=>'');
		foreach ($task as $key => $value) {
			
			$text=trim((string)$value);
			$ta['html'].=$text;
			$ta['listoftags'][]=$text;
			if( strpos($text,'<div class="title">Задание')!==false) {
				// здесь есть таймеры для бонусов.
				$ta['header']=$text;
				if(preg_match('#<div class="title">Задание (\d+)#', $text,$m)) {
					$ta['task_number']=(int)$m[1];
				}
				$ta['nextValueMain']=true;
				if(preg_match('#\([^)(0-9\-]*?((?:\s{0,}\d+ мин.)+)\s*\)#', $text,$m)) {
					$bonuses=explode('мин.',$m[1]);
					foreach ($bonuses as $k => $v) {
						if(is_numeric(trim($v))) {
							$ta['bonuses'][$k+1]=trim($v);
						}
					}
				}
				if(preg_match('#найдено кодов: (?P<fined>\d+) из (?P<total>\d+)(?:, для прохождения этого задания достаточно найти (?P<need>\d+))?#', $text, $m)) {
					if(isset($m['fined'])) $ta['finedCodes']['fined']=$m['fined'];
					if(isset($m['total'])) $ta['finedCodes']['total']=$m['total'];
					if(isset($m['need'])) $ta['finedCodes']['need']=$m['need'];

				}
			}elseif(isset($ta['nextValueMain'])) {
				unset($ta['nextValueMain']);
				$ta['main']=$text;
				$ta['spoilerExist']=count($value['div[style=padding:15px;background-color:#505050;margin:10px;border:1px solid #d0d0d0;]']);
				if( strpos($text,'<div class="title" style="padding-left:0">Спойлер</div>')!==false) {//<input type=hidden name=action value=spoilerCode>
					//выдрать спойлер
					$spoyler=$value['div[style=padding:15px;background-color:#505050;margin:10px;border:1px solid #d0d0d0;]'];
					$ta['spoiler']=(string)$spoyler;
					$ta['spoilerOpen']=true;

					// <div style="padding:15px;background-color:#505050;margin:10px;border:1px solid #d0d0d0;">
				} elseif($ta['spoilerExist']) {
					$ta['spoilerOpen']=false;
				}
				
			}


			if(preg_match('#<div class="sysmsg" style="text-align:center">([\s\n\r\w\S]*)</div>#', $text,$m)) {
				//<div class=sysmsg style='text-align:center'><B>Код не принят.</B><br>Если вы уверены в правильности вводимого кода, проверьте, не выдано ли вам следующее задание. Может быть кто-то из вашей команды уже ввел этот код и вы пытаетесь отправить его повторно к пройденному уже уровню.</div>
				$ta['sysmsg']=trim(str_replace("<br>", "\n", $m[1]));
			}
			if( strpos($text,'<input name="skvoz" type="hidden" value="1">')!==false) {
				$ta['skvoznoe']=1;	
			}

			if(preg_match('#\<input name="level" type="hidden" value="(\d+)">#', $text,$m)) {
				$ta['level']=$m[1];
			}


			if( strpos($text,'<div class="title">Подсказка l:</div>')!==false) {
				$ta['nextValueP1']=true;
			} elseif(isset($ta['nextValueP1'])) {
				unset($ta['nextValueP1']);
				$ta['p1']=$text;
			}

			if( strpos($text,'<div class="title">Подсказка 2:</div>')!==false) {
				$ta['nextValueP2']=true;
			} elseif(isset($ta['nextValueP2'])) {
				unset($ta['nextValueP2']);
				$ta['p2']=$text;
			}


		}
		if(preg_match('/lvlid#([^#]+)#/', $ta['html'],$m)){
			$ta['lvlid']=$m[1];
		}
		if(!isset($ta['level']) && isset($ta['main'])) {
			$ta['level']='main';
		}
		$ta['codes']=$this->extract_codes($task);
		// highlight_string(print_r($ta,true));
		return $ta;
		// highlight_string(print_r($ta,true));
	}
	function extract_codes($task) {
		$out=array();
		foreach ($task as $key => $value) {
			if(preg_match('#основные коды\:#', $value)) {
				$codepan=explode('<strong>Коды сложности</strong><br>',$value)[1];
				$codepan=explode('<br>',$codepan);
				$lines=array();
				foreach ($codepan as  $val) {
					$line=explode(': ', $val);
					foreach ($line as $v) {
						if(trim($v)!='') {
							$lines[]=trim($v);
						}
					}
				}
				foreach ($lines as  $val) {
					if(preg_match('#^\s*(((<span style="color:red">(null|[1-3]\+?)</span>)|(null|[1-3]\+?))(,\s)?)+\s*$#', $val)) {
						// highlight_string($val.' - yes');
						$codes=explode(',', $val);
						$codeOut=array();
						$codeList=array();
						foreach ($codes as $k=>$code) {
							if(preg_match('#^\s*(<span style="color:red">(null|[1-3]\+?)</span>)\s*$#', $code,$m)) {
								$codeOut['done'][]=($k+1).') '.trim(strip_tags($code));
								$codeList[$k+1]=array('need'=>false,'ko'=>trim(strip_tags($code)));
							} else{
								$codeOut['undone'][]=($k+1).') '.trim(strip_tags($code));
								$codeList[$k+1]=array('need'=>true,'ko'=>trim(strip_tags($code)));
							}
						}
						if(count($codeList)) {
							$out[]=$codeList;
						}
					} elseif(trim(strip_tags($val))!='') {
						// highlight_string($val.' - no');
						$out[]=trim(strip_tags($val)).':';
					}
				}

			}
		}
		return $out;
	}
	function compile_codes($key,$inline=true) {
		$out=array();
		$separator=$inline?', ':"\n";
		foreach ($this->data['tasks'][$key]['codes'] as $k => $v) {
			if(is_array($v)) {
				$codeOut=array();
				foreach ($v as $index => $code) {
					if($code['need']) {
						$codeOut['undone'][]='*'.($index).')* '.$code['ko'];
					} else {
						$codeOut['done'][]=''.($index).') '.$code['ko'];
					}
				}

				if(isset($codeOut['done'])) {
					$out[]='Взяты:';
					$out[]=implode($separator,$codeOut['done']);
				}
				if(isset($codeOut['undone'])) {
					$out[]='Нужны:';
					$out[]=implode($separator,$codeOut['undone']);
				}

			} else {
				$out[]=$v;
			}
		}

		return implode("\n",$out);

	}
	function compile_left_codes($key,$inline=true) {
		$out=array();
		$separator=$inline?', ':"\n";
		foreach ($this->data['tasks'][$key]['codes'] as $k => $v) {
			if(is_array($v)) {
				$codeOut=array();
				foreach ($v as $index => $code) {
					if($code['need']) {
						$codeOut['undone'][]='<b>'.($index).')</b> '.$code['ko'];
					} else {
						$codeOut['done'][]=''.($index).') '.$code['ko'];
					}
				}
/*
				if(isset($codeOut['done'])) {
					$out[]='Взяты:';
					$out[]=implode($separator,$codeOut['done']);
				}*/
				if(isset($codeOut['undone'])) {
					// $out[]='Нужны:';
					$out[]=implode($separator,$codeOut['undone']);
				}

			} else {
				$out[]=$v;
			}
		}
		$tmp=$out;
		$out=array();
		$lastCool=false;
		foreach ($tmp as $k => $value) {
			if(substr($value, -1)==':') {
				if($lastCool) {
					$out[count($out)-1]=str_replace(':__', ' &gt; ', $out[count($out)-1].'__'.$value);
				} else {
					$out[]=$value;
					$lastCool=true;
				}
			} else {
				$lastCool=false;
				$out[]=$value;
			}
		}

		if(isset($this->data['tasks'][$key]['finedCodes']['need'])) {
			$out[]='Достаточно <b>'.$this->data['tasks'][$key]['finedCodes']['need'].'</b> из '.$this->data['tasks'][$key]['finedCodes']['total'].'. Осталось - <b>'.(isset($this->data['tasks'][$key]['finedCodes']['need'])?$this->data['tasks'][$key]['finedCodes']['need']-$this->data['tasks'][$key]['finedCodes']['fined']:$this->data['tasks'][$key]['finedCodes']['total']-$this->data['tasks'][$key]['finedCodes']['fined']).'</b>';
		} elseif(isset($this->data['tasks'][$key]['finedCodes']['total'])) {
			$out[]='Всего <b>'.$this->data['tasks'][$key]['finedCodes']['total'].'</b>. Осталось - <b>'.($this->data['tasks'][$key]['finedCodes']['total']-$this->data['tasks'][$key]['finedCodes']['fined']).'</b>';
		} else {
			// $out[]='1<pre>'.print_r($this->data['tasks'][$key],true).'</pre>2';
		}
		if(isset($this->data['timerEnd']) && $this->data['timerEnd']-time()>0) {
			$left=$this->data['timerEnd']-time();
			if(isset($this->data['tasks'][$key]['p2'])) {
				$out[]='До конца уровня: <b>'.floor($left/60).':'.str_pad(($left%60), 2,'0',STR_PAD_RIGHT).'</b>';
			} elseif(isset($this->data['tasks'][$key]['p1'])) {
				$out[]='До второй подсказки: <b>'.floor($left/60).':'.str_pad(($left%60), 2,'0',STR_PAD_RIGHT).'</b>';
			} else {
				$out[]='До первой подсказки: <b>'.floor($left/60).':'.str_pad(($left%60), 2,'0',STR_PAD_RIGHT).'</b>';
			}
		} else {
			$out[]=$left;
			$out[]=$this->data['timerEnd'];
			$out[]=time();
		}

		return implode("\n",$out);

	}
}