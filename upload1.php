<?php
$serverName = "localhost"; 	//数据库服务器地址
$uid = "sa"; 				//数据库用户名
$pwd = "Qwertyuiop0"; 		//数据库密码 Qwertyuiop0
$key = "q0m3sd8l";

			
$data = $_POST["data"];		//获取post过来的数据
if($data!=""){
	$data = json_decode(decrypt($data,$key,$key));
	//上传文件
	if($data->action == "upload1"){
		$tid = $data->tid;
		$sha1 = $data->filesha1;
		//从数据库中读取是否有这个sha1
		try {
			$connection = array("UID"=>$uid, "PWD"=>$pwd, "Database"=>"P2P");
			$conn = sqlsrv_connect($serverName, $connection);
			if($conn == false)
			{
				$jdata['result'] = "3";
				$jdata['content'] = "数据库连接失败";
				$jdata['query'] = "";
				echo encrypt(json_encode($jdata),$key,$key);
				exit;
			}
		} catch (Exception $e) {
			$jdata['result'] = "3";
			$jdata['content'] = "数据库连接异常";
			$jdata['query'] = "";
			echo encrypt(json_encode($jdata),$key,$key);
			exit;
		}
		
		//从数据库中查询是否有这条数据
		$query = sqlsrv_query($conn, "select * from mfile where tid = '$tid' and sha1='".$sha1."'");
		$row = sqlsrv_fetch_array($query);
		if(count($row)>0){
			//判断是否上传完成
			if($row['isfull'] == "1"){
				$filePath = $row['furl'];
				if(file_exists($filePath)){
					//文件存在
					$jdata['result'] = "121";
					$jdata['content'] = "文件存在";
					$jdata['query'] = "";
					echo encrypt(json_encode($jdata),$key,$key);
					exit;
				}else{
					//如果没有段数据，文件被删除
					$jdata['result'] = "124";
					$jdata['content'] = "文件被删除请重新上传";
					$jdata['query'] = "";
					echo encrypt(json_encode($jdata),$key,$key);
					exit;
				}
			}else{
				//文件不存在判断是否有段数据
				$furls = $row['furl'];
				$duan = $row['duan'];
				if(file_exists($furls)){
					//如果有段数据，没有上传完
					$jdata['result'] = "122";
					$jdata['content'] = "上传中";
					$jdata['query'] = "$duan";
					echo encrypt(json_encode($jdata),$key,$key);
					exit;
				}else{
					//如果没有段数据，文件被删除
					$jdata['result'] = "124";
					$jdata['content'] = "文件被删除请重新上传";
					$jdata['query'] = "";
					echo encrypt(json_encode($jdata),$key,$key);
					exit;
				}
			}
		}else{
			$jdata['result'] = "0";
			$jdata['content'] = "没有上传过";
			$jdata['query'] = "";
			echo encrypt(json_encode($jdata),$key,$key);
			exit;
		}
	}
}else{
	$jdata['result'] = "3";
	$jdata['content'] = "参数不正确";
	$jdata['query'] = "";
	echo encrypt(json_encode($jdata),$key,$key);
	exit;
}






/**加密*/
function encrypt($str, $key = "q0m3sd8l", $iv = "q0m3sd8l")
{
	$size = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_CBC);
	$str = pkcs5Pad($str, $size);
	$data = mcrypt_cbc(MCRYPT_DES, $key, $str, MCRYPT_ENCRYPT, $iv);
	return base64_encode($data);
}
/**解密*/
function decrypt($str, $key = "q0m3sd8l", $iv = "q0m3sd8l")
{
	$str = base64_decode($str);
	$str = mcrypt_cbc(MCRYPT_DES, $key, $str, MCRYPT_DECRYPT, $iv);
	$str = pkcs5Unpad($str);
	return $str;
}
function pkcs5Pad($text, $blocksize)
{
	$pad = $blocksize - (strlen($text) % $blocksize);
	return $text . str_repeat(chr($pad), $pad);
}
function pkcs5Unpad($text)
{
	$pad = ord($text{strlen($text) - 1});
	if ($pad > strlen($text))
		return false;
	if (strspn($text, chr($pad), strlen($text) - $pad) != $pad)
		return false;
	return substr($text, 0, -1 * $pad);
}