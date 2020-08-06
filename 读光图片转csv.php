<?php
$suffix = ['jpg', 'png'];
$array = find_file('.');
$err = [];
for ($offset = 0; $offset < count($array); $offset++) {
    $value = $array[$offset];
    if (!in_array(getsuffix($value), $suffix)) {
        echo "$offset 忽略:$value\n";
        continue;
    }
    if (file_exists(getfilename($value) . '.xlsx') && filesize(getfilename($value) . '.xlsx') > 100) {
        echo "$offset 存在:$value\n";
        continue;
    }
    if (file_exists(getfilename($value) . '.csv') && filesize(getfilename($value) . '.csv') > 100) {
        echo "$offset 存在:$value\n";
        continue;
    }
    if (img2xls($value)) {
        echo "$offset 完成:$value\n";
    } else {
        echo "$offset 失败:$value\n";
        $err[] = $value;
    }
}
echo "处理完成\n";
print_r($err);
//遍历文件
function find_file($path)
{
    if (!is_dir($path)) {
        return [];
    }
    if ($fd = opendir($path)) {
        $arr = [];
        while ($file = readdir($fd)) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $file = $path . '/' . $file;
            if (is_dir($file)) {
                $arr = array_merge($arr, find_file($file));
            } else {
                $arr[] = $file;
            }
        }
        return $arr;
    }
    return [];
}

//读光
function img2xls($filepath)
{
    $appcode='';//todo your code
    $ch = curl_init();
    $url="https://ocrapi-document-structure.taobao.com/ocrservice/documentStructure";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: APPCODE '.$appcode
    ));
    if (1 == strpos("$" . $url, "https://")) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    $json['img'] = base64_encode(file_get_contents($filepath));
    $json['row'] = true;
    $json['table'] = true;
    $json['removeBoundary'] = true;

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
    $res = curl_exec($ch);

    $json = json_decode($res, true);
    curl_close($ch);
    if (!isset($json['content'])) {
        echo "$res\n";
        return false;
    }
    $csvArr=dealData($json['prism_tablesInfo']);
    saveByCsv($csvArr,getfilename($filepath));
    return true;
}

function getfilename($file)
{
    $pos = mb_strrpos($file, '.');
    return mb_substr($file, 0, $pos);
}

function getsuffix($file)
{
    $pos = mb_strrpos($file, '.');
    return mb_substr($file, $pos + 1);
}


function dealData(array $arr) :array {
    $csvArr=[];
    foreach ($arr as $sheet){
        if ($sheet['cellInfos']){
            $csv=[];
            foreach ($sheet['cellInfos'] as $key => $value) {
                $csv[$value['ysc']][$value['xsc']]=$value['word'];
            }
            //补空值占位
            for ($i=0; $i <= $sheet['yCellSize']; $i++) { 
               for ($i2=0; $i2 <=$sheet['xCellSize'] ; $i2++) { 
                   if (!isset($csv[$i])) {
                    !$csv[$i]=[];
                   }
                   if (!isset($csv[$i][$i2])) {
                    $csv[$i][$i2]='';
                   }
               }
            }
            $csvArr[]=$csv;
        }
    }
    return $csvArr;
}
function saveByCsv($arr,$path='default'){
    $fh=fopen($path.'.csv','w') or die("Can't open ".$path.'.csv');
    foreach ($arr as $key => $value) {
        if  ($key){
            fputcsv($fh, ["next sheet","$key"]);
        }
        foreach ($value as $sales_line){
            if (fputcsv($fh, $sales_line) === false) {
                die("Can't write CSV line");
            }
        }
    }
    fclose($fh) or die("Can't close ".$path.'.csv');
}

