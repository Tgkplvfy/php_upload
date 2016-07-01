<?php
$serverName = "localhost"; 	//数据库服务器地址
$uid = "sa"; 				//数据库用户名
$pwd = "Qwertyuiop0"; 		//数据库密码 Qwertyuiop0
$key = "q0m3sd8l";


$data = $_POST["data"];		//获取post过来的数据
//链接数据库
$conn = null;
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

if($data!=""){
	$data = json_decode(decrypt($data,$key,$key));
	//上传文件
	if($data->action == "upload2") {
		$tid = $data->tid;						//t的id
		$fullfilesha1 = $data->fullfilesha1;	//整个文件的sha1
		$alonefilesha1 = $data->alonefilesha1;	//当前段文件的sha1
		$zduan = $data->zduan;					//文件总段数
		$duan = $data->duan;					//当前发送的子文件是第几段
		$filename = $data->filename;			//文件名
		$furl = "Public/upload/".$fullfilesha1."/".$_FILES["mfile"]["name"];
		//判断文件是否有误
		if($_FILES["mfile"]["error"] > 0){
			$jdata['result'] = "1";
			$jdata['content'] = "文件不正确";
			$jdata['query'] = "";
			echo encrypt(json_encode($jdata),$key,$key);
			exit;
		}
		else{
			//判断文件夹是否存在
			$path = "Public/upload/".$fullfilesha1;
			if(!is_dir($path)){
				$res=mkdir(iconv("UTF-8", "GBK", $path),0777,true); 
				if (!$res){
					$jdata['result'] = "2";
					$jdata['content'] = "文件夹创建失败";
					echo encrypt(json_encode($jdata),$key,$key);
					exit;
				}else{
					//如果文件夹创建成功，进行第一次上传
					if(move_uploaded_file($_FILES["mfile"]["tmp_name"],$furl)){
						$mergesha1 = sha1_file($furl);
						if($alonefilesha1 == $mergesha1){
							if($zduan == "1"){
								//如果是最后一段文件 合并文件 更新DB状态
								//开始合并文件
								$farr = explode(".",$filename);
								$mergename = "Public/upload/".$fullfilesha1."/".$filename;
								$fp = fopen($mergename,"a+");
								for($i=0;$i<$zduan;$i++){  
									$childfile = "Public/upload/".$fullfilesha1."/".$farr[0]."-".$i.".dat";
									$handle = fopen($childfile,"a+");  
									fwrite($fp,fread($handle,filesize($childfile)));  
									fclose($handle);  
									unset($handle);
									unlink($childfile);
								}
								fclose($fp);
								$mergesha1 = sha1_file($mergename);
								$sql = "insert into mfile(fname,fuptime,fdowntime,furl,sha1,tid,duan,isfull)values(?,?,?,?,?,?,?,?)";
								$params = array();
								$params[0] = $filename;
								$params[1] = date("Y-m-d H:i:s");
								$params[2] = null;
								$params[3] = $mergename;
								$params[4] = $fullfilesha1;
								$params[5] = $tid;
								$params[6] = 1;
								$params[7] = 1;
								sqlsrv_query( $conn, $sql, $params);
								//删除分块文件
								for($i=0;$i<$zduan;$i++){  
									$childfile = "Public/upload/".$fullfilesha1."/".$farr[0]."-".$i.".dat";
									unlink($childfile);
								}
								//合并完成检查sha1，如果sha1一致，则提示终端文件上传成功
								$jdata['result'] = "10";
								$jdata['content'] = "文件上传成功!";
								$jdata['query'] = "";
								echo encrypt(json_encode($jdata),$key,$key);
								exit;
							}else{
								$sql = "insert into mfile(fname,fuptime,fdowntime,furl,sha1,tid,duan,isfull)values(?,?,?,?,?,?,?,?)";
								$params = array();
								$params[0] = $filename;
								$params[1] = date("Y-m-d H:i:s");
								$params[2] = null;
								$params[3] = $furl;
								$params[4] = $fullfilesha1;
								$params[5] = $tid;
								$params[6] = 1;
								$params[7] = 0;
								sqlsrv_query( $conn, $sql, $params);
								
								$jdata['result'] = "4";
								$jdata['content'] = "第".$duan."段文件上传成功!";
								$jdata['query'] = "";
								echo encrypt(json_encode($jdata),$key,$key);
								exit;
							}
						}else{
							unlink($furl);
							$jdata['result'] = "3";
							$jdata['content'] = "第".$duan."段文件上传失败!";
							$jdata['query'] = $duan;
							echo encrypt(json_encode($jdata),$key,$key);
							exit;
						}
					}else{
						$jdata['result'] = "3";
						$jdata['content'] = "第".$duan."段文件上传失败!";
						$jdata['query'] = $duan;
						echo encrypt(json_encode($jdata),$key,$key);
						exit;
					}
				}
			}else{
				//判断数据库中是否存在
				$sql = "select * from mfile where sha1 = '$fullfilesha1'";
				$rs = sqlsrv_query( $conn, $sql);
				$row = sqlsrv_fetch_array($rs);
				if($row == 0){
					//数据库中不存在 进行第一次上传
					if(move_uploaded_file($_FILES["mfile"]["tmp_name"],$furl)){
						$mergesha1 = sha1_file($furl);
						if($alonefilesha1 == $mergesha1){
							if($zduan == "1"){
								//如果是最后一段文件 合并文件 更新DB状态
								//开始合并文件
								$farr = explode(".",$filename);
								$mergename = "Public/upload/".$fullfilesha1."/".$filename;
								$fp = fopen($mergename,"a+");
								for($i=0;$i<$zduan;$i++){  
									$childfile = "Public/upload/".$fullfilesha1."/".$farr[0]."-".$i.".dat";
									$handle = fopen($childfile,"a+");  
									fwrite($fp,fread($handle,filesize($childfile)));  
									fclose($handle);  
									unset($handle);
									unlink($childfile);
								}
								fclose($fp);
								$mergesha1 = sha1_file($mergename);
								$sql = "insert into mfile(fname,fuptime,fdowntime,furl,sha1,tid,duan,isfull)values(?,?,?,?,?,?,?,?)";
								$params = array();
								$params[0] = $filename;
								$params[1] = date("Y-m-d H:i:s");
								$params[2] = null;
								$params[3] = $mergename;
								$params[4] = $fullfilesha1;
								$params[5] = $tid;
								$params[6] = 1;
								$params[7] = 1;
								sqlsrv_query( $conn, $sql, $params);
								//删除分块文件
								for($i=0;$i<$zduan;$i++){  
									$childfile = "Public/upload/".$fullfilesha1."/".$farr[0]."-".$i.".dat";
									unlink($childfile);
								}
								//合并完成检查sha1，如果sha1一致，则提示终端文件上传成功
								$jdata['result'] = "10";
								$jdata['content'] = "文件上传成功!";
								$jdata['query'] = "";
								echo encrypt(json_encode($jdata),$key,$key);
								exit;
							}else{
								$sql = "insert into mfile(fname,fuptime,fdowntime,furl,sha1,tid,duan,isfull)values(?,?,?,?,?,?,?,?)";
								$params = array();
								$params[0] = $filename;
								$params[1] = date("Y-m-d H:i:s");
								$params[2] = null;
								$params[3] = $furl;
								$params[4] = $fullfilesha1;
								$params[5] = $tid;
								$params[6] = 1;
								$params[7] = 0;
								sqlsrv_query( $conn, $sql, $params);
								
								$jdata['result'] = "4";
								$jdata['content'] = "第".$duan."段文件上传成功!";
								$jdata['query'] = "";
								echo encrypt(json_encode($jdata),$key,$key);
								exit;
							}
						}else{
							unlink($furl);
							$jdata['result'] = "3";
							$jdata['content'] = "第".$duan."段文件上传失败!";
							$jdata['query'] = $duan;
							echo encrypt(json_encode($jdata),$key,$key);
							exit;
						}
					}else{
						$jdata['result'] = "3";
						$jdata['content'] = "第".$duan."段文件上传失败!";
						$jdata['query'] = $duan;
						echo encrypt(json_encode($jdata),$key,$key);
						exit;
					}
				}else{
					//数据库中存在
					//判断文件是否已经存在
					if(!file_exists($furl))
					{
						//如果文件不存在
						//把文件放入指定文件夹
						if(move_uploaded_file($_FILES["mfile"]["tmp_name"],$furl)){
							//判断上传后的片段的sha1值和上传前的sha1值是否相等
							$mergesha1 = sha1_file($furl);
							if($mergesha1 == $alonefilesha1){
								if($duan == "0"){
									//如果是第一段
									$sql = "insert into mfile(fname,fuptime,fdowntime,furl,sha1,tid,duan,isfull)values(?,?,?,?,?,?,?,?)";
									$params = array();
									$params[0] = $filename;
									$params[1] = date("Y-m-d H:i:s");
									$params[2] = null;
									$params[3] = $furl;
									$params[4] = $mergesha1;
									$params[5] = $tid;
									$params[6] = 1;
									$params[7] = 0;
									sqlsrv_query( $conn, $sql, $params);
									$jdata['result'] = "4";
									$jdata['content'] = "第".$duan."段文件上传成功!";
									$jdata['query'] = "";
									echo encrypt(json_encode($jdata),$key,$key);
									exit;
								}else if($zduan - 1 > $duan){
									//如果不是最后一段文件 更新DB状态
									$sql = "update mfile set furl = '$furl' , duan = '$duan' where sha1 = '$fullfilesha1'";
									sqlsrv_query($conn, $sql);
									$jdata['result'] = "4";
									$jdata['content'] = "第".$duan."段文件上传成功!";
									$jdata['query'] = "";
									echo encrypt(json_encode($jdata),$key,$key);
									exit;
								}else{
									//如果是最后一段文件 合并文件 更新DB状态
									//开始合并文件
									$farr = explode(".",$filename);
									$mergename = "Public/upload/".$fullfilesha1."/".$filename;
									$fp = fopen($mergename,"a+");
									for($i=0;$i<$zduan;$i++){  
										$childfile = "Public/upload/".$fullfilesha1."/".$farr[0]."-".$i.".dat";
										$handle = fopen($childfile,"a+");  
										fwrite($fp,fread($handle,filesize($childfile)));  
										fclose($handle);  
										unset($handle);
										unlink($childfile);
									}
									fclose($fp);
									$mergesha1 = sha1_file($mergename);
									if($mergesha1==$fullfilesha1){
										$sql = "update mfile set furl = '$mergename' , duan = '$duan' , isfull = 1 where sha1 = '$fullfilesha1'";
										sqlsrv_query($conn, $sql);
										//删除分块文件
										for($i=0;$i<$zduan;$i++){  
											$childfile = "Public/upload/".$fullfilesha1."/".$farr[0]."-".$i.".dat";
											unlink($childfile);
										}
										//合并完成检查sha1，如果sha1一致，则提示终端文件上传成功
										$jdata['result'] = "10";
										$jdata['content'] = "文件上传成功!";
										$jdata['query'] = "";
										echo encrypt(json_encode($jdata),$key,$key);
										exit;
									}else{
										unlink($mergename);
										$jdata['result'] = "-1";
										$jdata['content'] = "上传失败!请重新上传";
										$jdata['query'] = $duan;
										echo encrypt(json_encode($jdata),$key,$key);
										exit;
									}
								}
							}else{
								$jdata['result'] = "3";
								$jdata['content'] = "第".$duan."段文件上传失败!";
								$jdata['query'] = $duan;
								echo encrypt(json_encode($jdata),$key,$key);
								exit;
							}
						}else{
							$jdata['result'] = "3";
							$jdata['content'] = "第".$duan."段文件上传失败!";
							$jdata['query'] = $duan;
							echo encrypt(json_encode($jdata),$key,$key);
							exit;
						}
					}else{
						//如果文件存在
						$jdata['result'] = "5";
						$jdata['content'] = "第".$duan."段文件已经存在";
						$jdata['query'] = $duan;
						echo encrypt(json_encode($jdata),$key,$key);
						exit;
					}
				}
			}
		}
	}
}else{
	$jdata['result'] = "-1";
	$jdata['content'] = "参数不正确";
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