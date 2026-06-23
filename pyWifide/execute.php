<?php
/*
 * =====] py2php has license as                      ]=====
 *
 * + Source: https://github.com/dan-da/py2php
 *
 * + License: GPL-2.0
 *
 *
 * =====] PHPSandbox has license as                  ]=====
 *
 * Copyright (c) 2013 - 2016 by Corveda, LLC.
 *
 * + License: Custom
 *
 * + License URL: https://github.com/Corveda/PHPSandbox/blob/main/LICENSE
 *
 * + Source URL: https://github.com/Corveda/PHPSandbox
 *
 *
 * =====] CodeMirror has license as                  ]=====
 *
 * Copyright (C) 2017 by Marijn Haverbeke <marijn@haverbeke.berlin> and others
 *
 * + Product URL: https://codemirror.net/5/
 *
 * + License: MIT
 *
 * + License URL: https://codemirror.net/5/LICENSE
 *
 *
 * =====] jQuery on client side has license as       ]=====
 *
 * + License: MIT
 * 
 * + License URL: https://jquery.com/license/
 *
 * ========================================================
 *
 * =====] Following PHP functions has license as [=========
 *
 * + check_http_headers_for_mobile()
 * + g_match(string $regex, string $userAgent)
 * + match_user_agent_with_first_found_matching_rule( $userAgent )
 *
 * -----
 *
 * Copyright (c) 2021 Şerban Ghiţă, Nick Ilyin and contributors.
 *
 * + License: MIT
 * 
 * + License URL: https://github.com/serbanghita/Mobile-Detect/blob/4.x/LICENSE
 *
 * + Source URL: https://github.com/serbanghita/Mobile-Detect
 *
 * ========================================================
 *
 * =====] Other PHP, HTML & CSS codes has license as [=====
 *
 * Copyright (c) 2026 Dinh Thoai Tran <zinospetrel@sdf.org>
 * All rights reserved.
 *
 * + Source URL: https://github.com/progorker/pgk_pywifide/
 *
 * + License: GPL-2.0
 *
 * ========================================================
 */

session_start();
set_time_limit(0);
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

global $g_results, $g_config, $g_work_dir, $g_buffer_dir, $g_open_text, $g_source_text, $g_list_text, $g_remove_text, $g_workdir_text, $g_use_open, $g_open_cfg, $g_download_text, $g_load_text, $g_shp_text;

require_once __DIR__ . '/libs/phpsandbox/auto.php';
require_once __DIR__ . '/testor.php';
require_once __DIR__ . '/config.php';

$g_open_cfg = false;

function g_pywifide_help() {
  $text = <<<EOT
command\tdescription
#help\tDisplay this help.
#source\tInclude PHP script file which does not include '<?php ' & '?>'. Argument is .shp file path.
#pattern\tGet code pattern from myTestor.
#workdir\tSet work dir. Argument is selected directory.
#upload \tUpload zip file.
#download\tZip folder & download zip file. Argument is relative path.
#load  \tLoad script file into script editor. Argument is relative path.
#list  \tList buffer directory. Argument is relative path.
#remove\tRemove file. Argument is relative path.
#save  \tSave previous code to file. Does not execute script. Argument is relative path.
#cat   \tDisplay script file. Does not execute script. Argument is relative path.
#username\tSet Testor's username. It is executed in client side.
#password\tSet Testor's password. It is executed in client side.
#suite\tSet test suite code. It is executed in client side.
#tcexec\tTrigger to run unit test case via proxy. Arguments are 'script_path', 'func_name'.
EOT;
  $rs = '';
  g_pywifide_parse_results( $text, $rs );
  return $rs;
}

function g_pywifide_exec_sql( $sql, $decor = false ) {
  global $g_config, $g_use_open, $g_open_cfg;

  $host = $g_config['mytestor.host'];
  $port = $g_config['mytestor.port'];
  $user = $g_config['mytestor.username'];
  $pass = $g_config['mytestor.password'];
  $db = $g_config['mytestor.database'];

  $cmd = $g_config['mytestor.command'];
  if ( strpos( $cmd, 'mariadb' ) !== false ) {
    $cmd .= " --skip-ssl-verify-server-cert";
  } else if ( strpos( $cmd, 'mysql' ) !== false ) {
    $cmd .= " --ssl-mode=DISABLED";  
  }
  $uid = uniqid();
  $fn = $uid . '.sql';
  $ufn = __DIR__ . '/buffers/' . $fn;
  $dir = dirname( $ufn );
  @mkdir( $dir, 0777, true );
  @file_put_contents( $ufn, $sql );

  $query = "cd $dir && $cmd --disable-auto-rehash -h $host -P $port --user=$user --password=$pass -e \"use $db; source ./$fn ; \" ";
  $text = @shell_exec($query) . '';
  @unlink( $ufn );
  if ( $decor ) {
    $results = '';
    g_pywifide_parse_results( $text, $results );
    return $results;
  } else {
    return $text;
  }
}

function g_pywifide_escape( $sql ) {
  $sql = str_replace( "_", "_._us_._", $sql );
  $sql = str_replace( "\n", "__nl__", $sql );
  $sql = str_replace( "\r", "__cr__", $sql );
  $sql = str_replace( "\t", "__tb__", $sql );
  $sql = str_replace( "\\", "__sl__", $sql );
  $sql = str_replace( '"', "__dq__", $sql );
  $sql = str_replace( "'", "__sq__", $sql );
  $sql = str_replace( "`", "__td__", $sql );
  return $sql;
}

function g_pywifide_unescape( $sql ) {
  $sql = str_replace( "__nl__", "\n", $sql );
  $sql = str_replace( "__cr__", "\r", $sql );
  $sql = str_replace( "__tb__", "\t", $sql );
  $sql = str_replace( "__sl__", "\\", $sql );
  $sql = str_replace( "__dq__", '"', $sql );
  $sql = str_replace( "__sq__", "'", $sql );
  $sql = str_replace( "__td__", "`", $sql );
  $sql = str_replace( "_._us_._", "_", $sql );
  return $sql;
}

function g_pywifide_finds( $keys, $src, $start = 0 ) {
  $ret = [];
  $srt = [];
  $szl = [];
  $kyl = [];
  $pidx = -1;
  foreach ( $keys as $k ) {
    $idx = strpos( $src, $k, $start );
    if ( $idx !== false ) {
      $ret[] = $idx;
      $srt[] = $idx;
      $szl[] = strlen( $k );
      $kyl[] = $k;
    }
  }
  if ( count( $srt ) > 0 ) {
    sort( $srt );
    $v = $srt[0];
    for ( $i = 0; $i < count( $ret ); $i++ ) {
      if ( $ret[ $i ] == $v ) {
        $pidx = $i;
        break;
      }
    }
  }
  return array( 'idxl' => $ret, 'szl' => $szl, 'kyl' => $kyl, 'pidx' => $pidx );
}

function g_pywifide_pattern( $p_module, $p_kind, $p_code, $p_variant ) {
  $module = g_pywifide_escape( $p_module );
  $kind = g_pywifide_escape( $p_kind );
  $code = g_pywifide_escape( $p_code );
  $variant = g_pywifide_escape( $p_variant );
  $sql = "set @v_pattern = '_'; set @v_module = api_testor_unescape('$module'); set @v_kind = api_testor_unescape('$kind'); set @v_code = api_testor_unescape('$code'); set @v_variant = api_testor_unescape('$variant'); call api_testor_pattern( @v_module, @v_kind, @v_code, @v_variant, @v_pattern ); select @v_pattern as pattern\\G";
  $text = g_pywifide_exec_sql( $sql, false );
  $idx = strpos( $text, 'pattern:' );
  if ( $idx !== false ) {
    $text = substr( $text, $idx + 8 );
  }
  return $text;
}

function g_pywifide_load_help( $sql ) {
  global $g_buffer_dir;
  
  $has_help = false;
  $nsql = '';
  $start = 0;
  $finds = [' #help ', "\n".'#help ', "\n".'#help'."\n" ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $has_help = true;
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_help( $nsql );
  }
  if ( $has_help ) {
    $nsql = "\n# loadhelp #\n" . $nsql;
  }
  return $nsql;
}

function g_pywifide_load_cat( $sql ) {
  global $g_buffer_dir;
  
  $has_cat = false;
  $nsql = '';
  $start = 0;
  $finds = [' #cat ', "\n".'#cat ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    $cat = trim( @file_get_contents( $g_buffer_dir . '/' . $filename ) );
    if ( $cat !== '' ) {
      $has_cat = true;
      $nsql .= "\n" . $cat . "\n";
    }
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_cat( $nsql );
  }
  if ( $has_cat ) {
    $nsql = "\n# loadcat #\n" . $nsql;
  }
  return $nsql;
}

function g_pywifide_load_load( $sql ) {
  global $g_config, $g_buffer_dir, $g_load_text;
  
  $nsql = '';
  $start = 0;
  $finds = [' #load ', "\n".'#load ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    $proxy_url = $g_config['mytestor.proxy_url'];
    $proxy_token = $g_config['mytestor.proxy_token'];
    if ( strlen( $proxy_url ) > 0 ) {
      $load_url = "$proxy_url". "load.php?token=$proxy_token&f=$filename";
      $cat = @file_get_contents( $load_url );
      if ( $cat === null ) $cat = '';
    } else {
      $cat = trim( @file_get_contents( $g_buffer_dir . '/' . $filename ) );
    }
    if ( $cat !== '' ) {
      $g_load_text = "\n# loading #\n" . $cat;
      return '';
    }
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_load( $nsql );
  }
  return $nsql;
}

function g_pywifide_load_list( $sql ) {
  global $g_config, $g_buffer_dir, $g_list_text;
  
  $has_list = false;
  $nsql = '';
  $start = 0;
  $finds = [' #list ', "\n".'#list ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $proxy_url = $g_config['mytestor.proxy_url'];
    $proxy_token = $g_config['mytestor.proxy_token'];
    if ( strlen( $proxy_url ) > 0 ) {
      $filename = trim( $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = trim( $filename );
      $list_url = "$proxy_url". "list.php?token=$proxy_token&f=$filename";
      $cat = @file_get_contents( $list_url );
      if ( $cat === null ) $cat = '';
    } else {
      $filename = trim( $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = trim( $filename );
      $cmd = "ls -1 " . $g_buffer_dir . '/' . $filename;
      $dir = dirname( $g_buffer_dir . '/' . $filename );
      @mkdir( $dir, 0777, true );
      $cat = trim( @shell_exec( $cmd ) . '' );
    }
    if ( $cat === '' ) {
      $cat = '__BLANK__';
    }
    if ( $cat !== '' ) {
      $has_list = true;
      $cat = "[DIR] $filename" . "\n" . $cat;
      $rs = '';
      g_pywifide_parse_results( $cat, $rs );
      $g_list_text .= "\n" . $rs . "\n";
    }
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_list( $nsql );
  }
  if ( $has_list ) {
    $nsql = "\n# loadlist #\n" . $nsql;
  }
  return $nsql;
}

function g_pywifide_load_remove( $sql ) {
  global $g_config, $g_buffer_dir, $g_remove_text;
  
  $nsql = '';
  $start = 0;
  $finds = [' #remove ', "\n".'#remove ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    $kind = '';
    $proxy_url = $g_config['mytestor.proxy_url'];
    $proxy_token = $g_config['mytestor.proxy_token'];
    if ( strlen( $proxy_url ) > 0 ) {
      $remove_url = "$proxy_url". "remove.php?token=$proxy_token&f=$filename";
      $cat = @file_get_contents( $remove_url );
      if ( $cat === null ) $cat = '';
      $kind = $cat;
    } else {
      if ( is_dir( $g_buffer_dir . '/' . $filename ) ) {
        $dir = $g_buffer_dir . '/' . $filename;
        $cmd = "rm -rf $dir";
        $kind = '[DIR]';
        @shell_exec( $cmd );
      } else if ( is_file( $g_buffer_dir . '/' . $filename ) ) {
        @unlink( $g_buffer_dir . '/' . $filename );
        $kind = '[FILE]';
      }
    }
    $cat = "$kind Remove" . "\n" . $filename;
    $rs = '';
    g_pywifide_parse_results( $cat, $rs );
    $g_remove_text .= "\n" . $rs . "\n";
    $nsql .= "\n# loadremove #\n";
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_remove( $nsql );
  }
  return $nsql;
}

function g_pywifide_load_tcexec( $sql ) {
  global $g_config, $g_buffer_dir, $g_tcexec_text;

  $nsql = '';
  $start = 0;
  $finds = [' #tcexec ', "\n".'#tcexec ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $fields = explode( " ", $filename );
    if ( count( $fields ) >= 2 ) {
      $filename = $fields[0];
      $filename = trim( $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = trim( $filename );
      $func = $fields[1];
      $proxy_url = $g_config['mytestor.proxy_url'];
      $proxy_token = $g_config['mytestor.proxy_token'];
      if ( strlen( $proxy_url ) > 0 ) {
        $cmd = "\ng_pywifide_tcexec('$filename', '$func', " . 'g_token' . ", " . 'g_suite_id' . ")\n";
        $nsql .= $cmd;
      }
    }
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  return $nsql;
}

function g_pywifide_load_download( $sql ) {
  global $g_config, $g_buffer_dir, $g_download_text;
  
  $zip_cmd = $g_config['mytestor.zip_cmd'];
  
  $nsql = '';
  $start = 0;
  $finds = [' #download ', "\n".'#download ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    $proxy_url = $g_config['mytestor.proxy_url'];
    $proxy_token = $g_config['mytestor.proxy_token'];
    if ( strlen( $proxy_url ) > 0 ) {
      $code = substr( strrev( uniqid() ), 0, 4 );
      $dl_dir = __DIR__ . '/downloads';
      @mkdir( $dl_dir, 0777, true );
      $zip_file = $dl_dir . '/' . $code . '.zip';

      $download_url = "$proxy_url". "download.php?token=$proxy_token&f=$filename";
      $data = @file_get_contents( $download_url );
      @file_put_contents( $zip_file, $data );

      $zip_file = $code . '.zip';
      $uri = $_SERVER['REQUEST_URI'];
      $idx = strrpos( $uri, '/' );
      if ( $idx !== false ) {
        $uri = substr( $uri, 0, $idx );
      }
      $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
      $dl_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $uri . '/downloads/' . $zip_file;
      $cat = "[ Download ] " . $filename . "\n" . $dl_url;
      $rs = '';
      g_pywifide_parse_results( $cat, $rs );
      $g_download_text .= "\n" . $rs . "\n";
    } else {
      $src_dir = $g_buffer_dir . '/' . $filename;
      if ( is_dir( $src_dir ) ) {
        $tmp_dir = __DIR__ . '/tmp/' . uniqid();
        @mkdir( $tmp_dir, 0777, true );
        $code = substr( strrev( uniqid() ), 0, 4 );
        $zip_dir = $tmp_dir . '/' . $code;
        @mkdir( $zip_dir, 0777, true );
        $cmd = "cp -rf $src_dir/* $zip_dir/";
        @shell_exec( $cmd );
        $zip_file = $code . '.zip';
        $cmd = "cd $tmp_dir && $zip_cmd -r $zip_file $code";      
        @shell_exec( $cmd );
        $zip_file = $tmp_dir . '/' . $zip_file;
        $dl_dir = __DIR__ . '/downloads';
        @mkdir( $dl_dir, 0777, true );
        $cmd = "cp -f $zip_file $dl_dir/";
        @shell_exec( $cmd );
        $zip_file = $code . '.zip';
        $uri = $_SERVER['REQUEST_URI'];
        $idx = strrpos( $uri, '/' );
        if ( $idx !== false ) {
          $uri = substr( $uri, 0, $idx );
        }
        $protocol = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) ? 'https' : 'http';
        $dl_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $uri . '/downloads/' . $zip_file;
        $cat = "[ Download ] " . $filename . "\n" . $dl_url;
        $rs = '';
        g_pywifide_parse_results( $cat, $rs );
        $g_download_text .= "\n" . $rs . "\n";
        $cmd = "rm -rf $tmp_dir";
        @shell_exec( $cmd );
      }
    }
    $nsql .= "\n# loaddownload #\n";
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_download( $nsql );
  }
  return $nsql;
}

function g_pywifide_load_workdir( $sql ) {
  global $g_buffer_dir, $g_workdir_text, $g_work_dir;
  
  $nsql = '';
  $start = 0;
  $finds = [' #workdir ', "\n".'#workdir ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    if ( is_dir( $g_buffer_dir . '/' . $filename ) ) {
      $g_work_dir = $filename;
      $dir = $g_buffer_dir . '/' . $filename;
      $g_buffer_dir = $dir;
      $cat = "[DIR] Work" . "\n" . $filename;
      $rs = '';
      g_pywifide_parse_results( $cat, $rs );
      $g_workdir_text .= "\n" . $rs . "\n";
    }
    $nsql .= "\n# loadworkdir #\n";
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_workdir( $nsql );
  }
  return $nsql;
}

function g_pywifide_load_save( $sql ) {
  global $g_config, $g_buffer_dir;
  
  $has_save = false;
  $nsql = '';
  $start = 0;
  $finds = [' #save ', "\n".'#save ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    $fileext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    if ( $fileext === 'spy' ) {    
      $dir = @dirname( $g_buffer_dir . '/' . $filename ) . '';
      @mkdir( $dir, 0777, true );
      @file_put_contents( $g_buffer_dir . '/' . $filename, "\n" . trim( $nsql ) . "\n" );
      $has_save = true;
    }
    $proxy_url = $g_config['mytestor.proxy_url'];
    $proxy_token = $g_config['mytestor.proxy_token'];
    if ( strlen( $proxy_url ) > 0 ) {
      $tmp_dir = __DIR__ . '/tmp/' . uniqid();
      @mkdir( $tmp_dir, 0777, true );
      $save_file = $tmp_dir . '/' . uniqid() . '.file';
      @file_put_contents( $save_file, "\n" . trim( $nsql ) . "\n" );
      $curl_cmd = $g_config['mytestor.curl_cmd'];
      $fn = str_replace( '.', '__d__', $filename );
      $fn = str_replace( '/', '__s__', $fn );
      $upload_url = "$proxy_url". "save.php?token=$proxy_token&name=$fn";
      $cmd = "$curl_cmd -F " . '"' . "file=@$save_file" . '"' . " " . '"' . $upload_url . '"';
      @shell_exec( $cmd );
      $cmd = "rm -rf $tmp_dir";
      @shell_exec( $cmd );
      $has_save = true;
    }
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_save( $nsql );
  }
  if ( $has_save ) {
    $nsql = "\n# loadsave #\n" . $nsql;
  }
  return $nsql;
}

function g_pywifide_load_pattern( $sql ) {
  global $g_buffer_dir, $g_config;

  $nsql = '';
  $start = 0;
  $finds = [ ' #pattern ', "\n".'#pattern ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $fields = explode( " ", $filename );
    if ( count( $fields ) >= 5 ) {
      $module = $fields[0];
      $kind = $fields[1];
      $code = $fields[2];
      $variant = $fields[3];
      $filename = $fields[4];
      $filename = trim( $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = str_replace( '..', '', $filename );
      $filename = trim( $filename );
      $fileext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
      if ( $fileext === 'spy' ) {    
        $dir = @dirname( $g_buffer_dir . '/' . $filename ) . '';
        @mkdir( $dir, 0777, true );
        $pattern = "\n" . trim( g_pywifide_pattern( $module, $kind, $code, $variant ) ) . "\n";
        @file_put_contents( $g_buffer_dir . '/' . $filename, $pattern );
        $proxy_url = $g_config['mytestor.proxy_url'];
        $proxy_token = $g_config['mytestor.proxy_token'];
        if ( strlen( $proxy_url ) > 0 ) {
          $tmp_dir = __DIR__ . '/tmp/' . uniqid();
          @mkdir( $tmp_dir, 0777, true );
          $save_file = $tmp_dir . '/' . uniqid() . '.file';
          @file_put_contents( $save_file, "\n" . trim( $pattern ) . "\n" );
          $curl_cmd = $g_config['mytestor.curl_cmd'];
          $fn = str_replace( '.', '__d__', $filename );
          $fn = str_replace( '/', '__s__', $fn );
          $upload_url = "$proxy_url". "save.php?token=$proxy_token&name=$fn";
          $cmd = "$curl_cmd -F " . '"' . "file=@$save_file" . '"' . " " . '"' . $upload_url . '"';
          @shell_exec( $cmd );
          $cmd = "rm -rf $tmp_dir";
          @shell_exec( $cmd );
        }
      } 
    }
    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  $rets = g_pywifide_finds( $finds, $nsql, 0 );
  if ( count( $rets['idxl'] ) > 0 ) {
    $nsql = g_pywifide_load_pattern( $nsql );
  }
  return $nsql;
}

function g_pywifide_load_source( $sql ) {
  global $g_buffer_dir, $g_source_text;
  
  $nsql = '';
  $start = 0;
  $finds = [' #source ', "\n". '#source ' ];
  $finds_2 = [';', "\n", "\r"];
  $rets = g_pywifide_finds( $finds, $sql, $start );
  while ( count( $rets['idxl'] ) > 0 ) {
    $pidx = $rets['pidx'];
    $key = $rets['kyl'][$pidx];
    $idx = $rets['idxl'][$pidx];
    $sz = $rets['szl'][$pidx];
    $nsql .= substr( $sql, $start, $idx - $start );
    $rets_2 = g_pywifide_finds( $finds_2, $sql, $idx + $sz );
    $pidx_2 = $rets_2['pidx'];
    if ( count( $rets_2['idxl'] ) > 0 ) {
      $filename = substr( $sql, $idx + $sz, $rets_2['idxl'][$pidx_2] - $idx - $sz);
      $start = $rets_2['idxl'][$pidx_2] + $rets_2['szl'][$pidx_2];
      if ( $rets_2['kyl'][$pidx_2] == "\n" ) {
        $start -= 1;
      }
    } else {
      $filename = substr( $sql, $idx + $sz );
      $start = strlen( $sql );
    }
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );

    $script = "\n" . @file_get_contents( $g_buffer_dir . '/' . $filename ) . "\n";
    $script = g_pywifide_refine( $script );
    $nsql .= "\n# loadsrc #\n";
    $nsql .= $script;

    $rets = g_pywifide_finds( $finds, $sql, $start );
  }
  $nsql .= substr( $sql, $start );
  return $nsql;
}

function g_pywifide_refine( $sql ) {
  $sql = g_pywifide_load_save( "\n" . $sql . "\n" );
  if ( strpos( "\n" . $sql . "\n", "\n# loadsave #\n" ) === false ) {
    $sql = g_pywifide_load_workdir( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_source( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_pattern( "\n" . $sql . "\n" );

    $sql = g_pywifide_load_cat( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_list( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_remove( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_download( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_load( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_tcexec( "\n" . $sql . "\n" );
    $sql = g_pywifide_load_help( "\n" . $sql . "\n" );
  }

  return "\n" . trim( $sql ) . "\n";
}

function g_pywifide_fill_table( $cols, $rows, $fsz, &$p_results ) {
  $p_results .= "\n\n";
  $p_results .= '+';
  for ( $i = 0; $i < count( $cols ); $i++ ) {
    $p_results .= str_pad( '-', $fsz[ $i ] + 2, '-' ) . '+';
  }
  $p_results .= "\n";
  $p_results .= '|';
  for ( $i = 0; $i < count( $cols ); $i++ ) {
    $p_results .= str_pad( ' ' . $cols[ $i ] . ' ', $fsz[ $i ] + 2, ' ' ) . '|';
  }
  $p_results .= "\n";
  $p_results .= '+';
  for ( $i = 0; $i < count( $cols ); $i++ ) {
    $p_results .= str_pad( '-', $fsz[ $i ] + 2, '-' ) . '+';
  }
  $p_results .= "\n";
  for ( $j = 0; $j < count( $rows ); $j++ ) {
    $rw = $rows[ $j ];
    $p_results .= '|';
    for ( $i = 0; $i < count( $rw ); $i++ ) {
      $p_results .= str_pad( ' ' . $rw[ $i ] . ' ', $fsz[ $i ] + 2, ' ' ) . '|';
    }
    $p_results .= "\n";
  }
  $p_results .= '+';
  for ( $i = 0; $i < count( $cols ); $i++ ) {
    $p_results .= str_pad( '-', $fsz[ $i ] + 2, '-' ) . '+';
  }
  $p_results .= "\n";
}

function g_pywifide_parse_results( $text, &$p_results ) {
  $lines = explode( "\n", $text );
  $fld_cnt = -1;
  $cols = [];
  $rows = [];
  $fsz = [];
  $p_results = '';
  foreach ( $lines as $ln ) {
    if ( trim( $ln ) === '' ) continue;
    $fields = explode( "\t", $ln );
    if ( count( $fields ) !== $fld_cnt ) {
      if ( $fld_cnt > 0 ) {
        g_pywifide_fill_table( $cols, $rows, $fsz, $p_results );
      }
      $rows = [];
      $cols = [];
      $fsz = [];
      foreach ( $fields as $fd ) {
        $cols[] = $fd;
        $fsz[] = strlen( $fd );
      }
      $fld_cnt = count( $fields );
      continue;
    } 
    $rw = [];
    for ( $i = 0; $i < count( $fields ); $i++ ) {
      $fd = $fields[ $i ];
      $sz = strlen( $fd );
      if ( $sz > $fsz[ $i ] ) {
        $fsz[ $i ] = $sz;
      }
      $rw[] = $fd;
    }
    $rows[] = $rw;
  }
  if ( $fld_cnt > 0 ) {
    g_pywifide_fill_table( $cols, $rows, $fsz, $p_results );
  }
}

function g_pywifide_tcexec( $filename, $func, $token, $suite_id ) {
  global $g_config;
  $proxy_url = $g_config['mytestor.proxy_url'];
  $proxy_token = $g_config['mytestor.proxy_token'];
  if ( strlen( $proxy_url ) > 0 ) {
    $filename = trim( $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = str_replace( '..', '', $filename );
    $filename = trim( $filename );
    $tcexec_url = "$proxy_url". "tcexec.php?token=$proxy_token&s=$filename&f=$func&t=$token&i=$suite_id";
    $cat = @file_get_contents( $tcexec_url );
    if ( $cat === null ) $cat = '';
    echo $cat;
  }
}

function g_pywifide_api_testor_welcome() {
  $results = \phptestor\api_testor_welcome();
  return [ $results ];
}

function g_pywifide_api_testor_is_online( $p_token ) {
  $online = \phptestor\api_testor_is_online( $p_token );
  return [ $online ];
}

function g_pywifide_api_testor_has_right( $p_token, $p_right_code ) {
  $right = \phptestor\testor_has_right( $p_token, $p_right_code );
  return [ $right ];
}

function g_pywifide_api_testor_escape( $p_input ) {
  $p_output = \phptestor\api_testor_escape( $p_input );
  return [ $p_output ];
}

function g_pywifide_api_testor_unescape( $p_input ) {
  $p_output = \phptestor\api_testor_unescape( $p_input );
  return [ $p_output ];
}

function g_pywifide_api_testor_login( $p_username, $p_password ) {
  $p_token = '';
  \phptestor\api_testor_login( $p_token, $p_username, $p_password );
  return [ $p_token ];
}

function g_pywifide_api_testor_logout( $p_token ) {
  \phptestor\api_testor_logout( $p_token );
}

function g_pywifide_api_testor_current_user( $p_token ) {
  $p_user_id = -1;
  $p_username = '';
  \phptestor\api_testor_current_user( $p_token, $p_user_id, $p_username );
  return [ $p_user_id, $p_username ];
}

function g_pywifide_api_testor_user_rights( $p_token ) {
  $p_api_call = false;
  $p_user_make = false;
  $p_user_demo = false;
  $p_storage_full = false;
  \phptestor\api_testor_user_rights( $p_token, $p_api_call, $p_user_make, $p_user_demo, $p_storage_full );
  return [ $p_api_call, $p_user_make, $p_user_demo, $p_storage_full ];
}

function g_pywifide_api_testor_change_password( $p_token, $p_password ) {
  \phptestor\api_testor_change_password( $p_token, $p_password );
}

function g_pywifide_api_testor_create_user( $p_token, $p_username, $p_password, $p_api_call, $p_user_make, $p_user_demo, $p_quota ) {
  \phptestor\api_testor_create_user( $p_token, $p_username, $p_password, $p_api_call, $p_user_make, $p_user_demo, $p_quota );
}

function g_pywifide_api_testor_suite( $p_token, $p_code ) {
  $p_id = -1;
  \phptestor\api_testor_suite( $p_token, $p_id, $p_code );
  return [ $p_id ];
}

function g_pywifide_api_testor_case( $p_token, $p_suite_id, $p_code ) {
  $p_id = -1;
  \phptestor\api_testor_case( $p_token, $p_id, $p_suite_id, $p_code );
  return [ $p_id ];
}

function g_pywifide_api_testor_suite_case( $p_token, $p_suite_code, $p_case_code ) {
  $p_suite_id = -1;
  $p_case_id = -1;
  \phptestor\api_testor_suite_case( $p_token, $p_suite_id, $p_case_id, $p_suite_code, $p_case_code );
  return [ $p_suite_id, $p_case_id ];
}

function g_pywifide_api_testor_clean( $p_token, $p_id ) {
  \phptestor\api_testor_clean( $p_token, $p_id );
}

function g_pywifide_api_testor_test( $p_token, $p_suite_id, $p_case_id, $p_code, $p_condition, $p_message ) {
  $p_id = -1;
  \phptestor\api_testor_test( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_condition, $p_message );
  return [ $p_id ];
}

function g_pywifide_api_testor_finish( $p_token, $p_id, $p_beauty = false ) {
  $p_results = '';
  \phptestor\api_testor_finish( $p_token, $p_id, $p_results, $p_beauty );
  return [ $p_results ];
}

function g_pywifide_api_testor_result( $p_token, $p_id, $p_beauty = false ) {
  $p_results = '';
  \phptestor\api_testor_result( $p_token, $p_id, $p_results, $p_beauty );
  return [ $results ];
}

function g_pywifide_api_testor_option( $p_token, $p_suite_id, $p_code, $p_remove ) {
  \phptestor\api_testor_option( $p_token, $p_suite_id, $p_data, $p_code, $p_remove );
  return [ $p_data ];
}

function g_pywifide_api_testor_e_functions( $p_mysql_database, $p_find ) {
  $p_names = '';
  \phptestor\api_testor_e_functions( $p_mysql_database, $p_find, $p_names );
  return [ $p_names ];
}

function g_pywifide_api_testor_e_procedures( $p_mysql_database, $p_find ) {
  $p_names = '';
  \phptestor\api_testor_e_procedures( $p_mysql_database, $p_find, $p_names );
  return $p_names;
}

function g_pywifide_api_testor_e_tables( $p_mysql_database, $p_find ) {
  $p_names = '';
  \phptestor\api_testor_e_tables( $p_mysql_database, $p_find, $p_names );
  return [ $p_names ];
}

function g_pywifide_api_testor_version( $p_token, $p_suite_id, $p_cur_ver ) {
  \phptestor\api_testor_version( $p_token, $p_suite_id, $p_cur_ver );
}

function g_pywifide_api_testor_source( $p_token, $p_suite_id, $p_case_code, $p_beauty = false ) {
  $results = '';
  \phptestor\api_testor_source( $p_token, $p_suite_id, $p_case_code, $p_results, $p_beauty );
  return [ $results ];
}

function g_pywifide_api_testor_source_list( $p_token, $p_suite_id, $p_page_no, $p_beauty = false ) {
  $p_results = '';
  \phptestor\api_testor_source_list( $p_token, $p_suite_id, $p_page_no, $p_results, $p_beauty );
  return [ $p_results ];
}

function g_pywifide_api_testor_true( $p_token, $p_suite_id, $p_case_id, $p_code, $p_condition ) {
  $p_id = -1;
  \phptestor\api_testor_true( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_condition );
  return [ $p_id ];
}

function g_pywifide_api_testor_not_true( $p_token, $p_suite_id, $p_case_id, $p_code, $p_condition ) {
  $p_id = -1;
  \phptestor\api_testor_not_true( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_condition );
  return [ $p_id ];
}

function g_pywifide_api_testor_success( $p_token, $p_suite_id, $p_page_no, $p_beauty = false ) {
  $results = '';
  \phptestor\api_testor_success( $p_token, $p_suite_id, $p_page_no, $p_results, $p_beauty );
  return [ $results ];
}

function g_pywifide_api_testor_error( $p_token, $p_suite_id, $p_case_id, $p_code, $p_message ) {
  $p_id = -1;
  \phptestor\api_testor_error( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_message );
  return [ $p_id ];
}

function g_pywifide_api_testor_equals( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_equals( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_not_equals( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_not_equals( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_greater_than( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_greater_than( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_not_greater_than( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_not_greater_than( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_less_than( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_less_than( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_not_less_than( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_not_less_than( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_same( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_same( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_not_same( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_not_same( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_contains( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_contains( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_not_contains( $p_token, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value ) {
  $p_id = -1;
  \phptestor\api_testor_not_contains( $p_token, $p_id, $p_suite_id, $p_case_id, $p_code, $p_operand, $p_value );
  return [ $p_id ];
}

function g_pywifide_api_testor_failed( $p_token, $p_suite_id, $p_page_no, $p_beauty = false ) {
  $p_results = '';
  \phptestor\api_testor_failed( $p_token, $p_suite_id, $p_page_no, $p_results, $p_beauty );
  return [ $results ];
}

function g_pywifide_api_testor_man( $p_module, $p_kind, $p_code ) {
  $p_man = '';
  \phptestor\api_testor_man( $p_module, $p_kind, $p_code, $p_man );
  return [ $p_man ];
}

function g_pywifide_api_testor_pattern( $p_module, $p_kind, $p_code, $p_variant ) {
  $p_pattern = '';
  \phptestor\api_testor_pattern( $p_module, $p_kind, $p_code, $p_variant, $p_pattern );
  return [ $pattern ];
}

function g_pywifide_api_testor_startup() {
  \phptestor\api_testor_startup();
}

function g_pywifide_api_testor_shutdown() {
  \phptestor\api_testor_shutdown();
}

function g_pywifide_print( ...$src_list ) {
  $rs = "";
  foreach ( $src_list as $src ) {
    if ( $rs !== "" ) $rs .= " ";
    $rs .= g_pywifide_print_r( $src, false );
  }
  echo $rs . "\n";
}

readonly class pywifide_tuple implements ArrayAccess, Countable, IteratorAggregate {
  private array $elements;

  public function __construct(...$elements) {
    $this->elements = $elements;
  }

  public function offsetGet( mixed $offset ) : mixed {
    return $this->elements[ $offset ];
  }

  public function offsetSet( mixed $offset, mixed $value ) : void {

  }

  public function offsetUnset( mixed $offset ) : void {

  }

  public function offsetExists( mixed $offset ) : bool {
    return isset( $this->elements[ $offset ] );
  }

  public function count() : int {
    return count( $this->elements );
  }

  public function getIterator() : Traversable {
    return new ArrayIterator( $this->elements );
  }

  public function toArray() : array {
    return $this->elements;
  }
}

class pywifide_bytearray implements ArrayAccess, Countable, IteratorAggregate {
  private array $elements;
  private int $kind = 0;

  public function __construct(...$elements) {
    $this->elements = [];
    if ( count( $elements ) > 0 ) {
      if ( count( $elements ) > 0 ) {
        $el = $elements[0];
        if ( is_array( $el ) && count( $el ) == 1 && is_int( $el[0] ) ) {
          $el = $el[0];
        }
        if ( is_array( $el ) ) {
          $this->elements = $el;
        } else if ( is_string( $el ) ) {
          $a = explode( '', $el );
          $this->elements = $a;
        } else if ( is_int( $el ) ) {
          $this->kind = 1;
          for ( $i = 0; $i < $el; $i++ ) {
            array_push( $this->elements, 0 );
          }
        }
      }
    } 
  }

  public function offsetGet( mixed $offset ) : mixed {
    return $this->elements[ $offset ];
  }

  public function offsetSet( mixed $offset, mixed $value ) : void {
    $this->elements[ $offset ] = $value;
  }

  public function offsetUnset( mixed $offset ) : void {
    unset($this->elements[ $offset ]);
  }

  public function offsetExists( mixed $offset ) : bool {
    return isset( $this->elements[ $offset ] );
  }

  public function count() : int {
    return count( $this->elements );
  }

  public function getIterator() : Traversable {
    return new ArrayIterator( $this->elements );
  }

  public function toArray() : array {
    return $this->elements;
  }

  public function __toString() : string {
    $str = "";
    foreach ( $this->elements as $el ) {
      if ( is_string( $el ) ) {
        $str .= $el;
      } else if ( is_numeric( $el ) ) {
        if ( is_int( $el ) ) {
          $n = intval( $el );
          $n = $n % 256;
          if ( $n >= 32 && $n <= 126 ) {
            $str .= chr( $n );
          } else {
            $v1 = $n % 16;
            $v2 = ($n - $v1) / 16;
            $hexchars = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F' ];
            $str .= "\x" . $hexchars[$v2] . $hexchars[$v1];
          }
        } else {
          $str .= $el;
        }
      }
    }
    return "bytearray(b'" . $str . "')";
  }
}

function g_pywifide_tuple( ...$elements ) {
  return new pywifide_tuple( $elements );
}

function g_pywifide_bytearray( ...$elements ) {
  return new pywifide_bytearray( $elements );
}

function g_pywifide_print_r( $src, $newline = true ) {
  $rs = "";
  if ( is_array( $src ) ) {
    $rs = "[ ";
    $text = "";
    foreach ( $src as $it ) {
      if ( $text !== "" ) $text .= ", ";
      $text .= g_pywifide_print_r( $it, false ); 
    }
    $rs .= $text . " ]" . ( $newline ? "\n" : "" );
  } else if ( is_bool( $src ) ) {
    $rs .= ( $src ? "True" : "False" ) . ( $newline ? "\n" : "" );
  } else if ( is_object( $src ) ) {
    $cls = $src::class;
    if ( $cls === 'pywifide_tuple' ) {
      $rs = "( ";
      $text = "";
      foreach ( $src as $arr ) {
        foreach ( $arr as $it ) {
          if ( $text !== "" ) $text .= ", ";
          $text .= g_pywifide_print_r( $it, false ); 
        }
      }
      $rs .= $text . " )" . ( $newline ? "\n" : "" );
    } else if ( $cls === 'pywifide_bytearray' ) {
      $rs .= $src . ( $newline ? "\n" : "" );
    } else {
      $rs = print_r( $src, true ) . ( $newline ? "\n" : "" );
    }
  } else {
    $rs .= print_r( $src, true ) . ( $newline ? "\n" : "" );
  }
  return $rs;
}

function g_pywifide_abs( $src ) {
  return abs( $src );
}

function g_pywifide_floor( $src ) {
  return floor( $src );
}

function g_pywifide_all( $arr ) {
  foreach ( $arr as $it ) {
    if ( ! $it ) return false; 
  }
  return true;
}

function g_pywifide_any( $arr ) {
  foreach ( $arr as $it ) {
    if ( $it ) return true; 
  }
  return false;
}

function g_pywifide_bin( $n ) {
  $sign = 1;
  if ( $n < 0 ) {
    $sign = -1;
    $n = -$n;
  }
  $str = "";
  while ( $n >= 2 ) {
    $v = $n % 2;
    $str = $v . $str;
    $n = ( $n - $v ) / 2;
  }
  $str = $n . $str;
  if ( $sign == 1 ) {
    return "0b" . $str;
  } else {
    return "-0b" . $str;
  }
}

function g_pywifide_bool( $src = null ) {
  if ( $src === null ) {
    return false;
  } else if ( is_array( $src ) ) {
    if ( count( $src ) == 0 ) return false;
    return true;
  } else if ( is_numeric( $src ) ) {
    if ( $src === 0 ) return false;
    return true;
  } else if ( is_string( $src ) ) {
    if ( strlen( $src ) == 0 ) return false;
    return true;
  }
}

function g_pywifide_str( $src ) {
  return g_pywifide_print_r( $src, false );
}

function g_pywifide__valid_funcs( $func ) {
  $supported = [
"g_pywifide_str",
"g_pywifide_bytearray",
"g_pywifide_bool",
"g_pywifide_bin",
"g_pywifide_any",
"g_pywifide_all",
"g_pywifide_floor",
"g_pywifide_abs",
"g_pywifide_tuple",
"g_pywifide_print",
"g_pywifide_tcexec",
"g_pywifide_init",
"g_pywifide_vars",
"g_pywifide_range",
"g_pywifide_api_testor_escape",
"g_pywifide_api_testor_has_right",
"g_pywifide_api_testor_is_online",
"g_pywifide_api_testor_unescape",
"g_pywifide_api_testor_welcome",
"g_pywifide_api_testor_case",
"g_pywifide_api_testor_change_password",
"g_pywifide_api_testor_clean",
"g_pywifide_api_testor_contains",
"g_pywifide_api_testor_create_user",
"g_pywifide_api_testor_current_user",
"g_pywifide_api_testor_e_functions",
"g_pywifide_api_testor_e_procedures",
"g_pywifide_api_testor_equals",
"g_pywifide_api_testor_error",
"g_pywifide_api_testor_e_tables",
"g_pywifide_api_testor_failed",
"g_pywifide_api_testor_finish",
"g_pywifide_api_testor_greater_than",
"g_pywifide_api_testor_less_than",
"g_pywifide_api_testor_login",
"g_pywifide_api_testor_logout",
"g_pywifide_api_testor_man",
"g_pywifide_api_testor_not_contains",
"g_pywifide_api_testor_not_equals",
"g_pywifide_api_testor_not_greater_than",
"g_pywifide_api_testor_not_less_than",
"g_pywifide_api_testor_not_same",
"g_pywifide_api_testor_not_true",
"g_pywifide_api_testor_option",
"g_pywifide_api_testor_pattern",
"g_pywifide_api_testor_result",
"g_pywifide_api_testor_same",
"g_pywifide_api_testor_shutdown",
"g_pywifide_api_testor_source_list",
"g_pywifide_api_testor_source",
"g_pywifide_api_testor_startup",
"g_pywifide_api_testor_success",
"g_pywifide_api_testor_suite_case",
"g_pywifide_api_testor_suite",
"g_pywifide_api_testor_test",
"g_pywifide_api_testor_true",
"g_pywifide_api_testor_user_rights",
"g_pywifide_api_testor_version"
];

  if ( in_array( $func, $supported ) ) return true;
  return false;
}

function g_pywifide_range( $count ) {
  $rng = [];
  for ( $i = 0; $i < $count; $i++ ) {
    $rng[] = $i;
  }
  return $rng;
}

function g_pywifide__invalid_funcs( $func ) {
  $supported = [
'g_pywifide__py2php',
'g_pywifide__python_exec', 
'g_pywifide_param', 
'g_pywifide__invalid_funcs', 
'g_pywifide__valid_funcs',
'g_pywifide_help',
'g_pywifide_exec_sql',
'g_pywifide_escape',
'g_pywifide_unescape',
'g_pywifide_finds',
'g_pywifide_pattern',
'g_pywifide_load_help',
'g_pywifide_load_cat',
'g_pywifide_load_load',
'g_pywifide_load_list',
'g_pywifide_load_remove',
'g_pywifide_load_download',
'g_pywifide_load_workdir',
'g_pywifide_load_save',
'g_pywifide_load_pattern',
'g_pywifide_load_source',
'g_pywifide_loadtcexec',
'g_pywifide_refine',
'g_pywifide_fill_table',
'g_pywifide_parse_results'
];
  if ( in_array( $func, $supported ) ) return true;
  return false;
}

function g_pywifide__py2php( $script ) {
  global $g_config, $g_results;
  $py_cmd = $g_config['mytestor.python_cmd'];
  $py_scrp = __DIR__ . '/libs/py2php/py2php.py';
  $tmp_dir = __DIR__ . '/tmp/' . uniqid();
  @mkdir( $tmp_dir, 0777, true );
  $src_scrp = $tmp_dir . '/' . uniqid() . '.py';
  @file_put_contents( $src_scrp, $script );
  $cmd = "$py_cmd " . '"' . "$py_scrp" . '" "' . "$src_scrp" . '"';
  $script = @shell_exec( $cmd );
  $cmd = "rm -rf $tmp_dir";
  @shell_exec( $cmd );
  return $script;
}

function g_pywifide__python_exec( $sql ) {
  global $g_results, $g_shp_text, $g_sql_text;

  $g_sql_text = $sql;
  $script = g_pywifide__py2php( $sql );
  $g_shp_text .= "\n// loadshp //\n" . $script;

  register_shutdown_function( function() {
    global $g_results, $g_shp_text, $g_sql_text;
    $error = error_get_last();
    if ( $error !== null && in_array( $error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR] ) ) {
      $g_results .= "\n=====] FAILED [=====\n\n" . print_r( $error, true ) . "\n";
      echo $g_results;
      if ( strpos( "\n" . $g_sql_text . "\n", "\n# shp #\n" ) !== false ) {
        if ( strlen( $g_shp_text ) > 0 ) {
          if ( strpos( "\n" . $g_shp_text . "\n", "\n// loadshp //\n" ) !== false ) {
             echo "\n", "=====] SHP [=====", "\n", str_replace( "\n// loadshp //\n", '', $g_shp_text), "\n", "====================", "\n";
          }
        }
      }
      exit();
    }
  } );

  ob_start();
  $sandbox = new PHPSandbox\PHPSandbox;
  $sandbox->setFuncValidator( function( $func, PHPSandbox\PHPSandbox $sandbox ) {
    if ( g_pywifide__invalid_funcs( $func ) ) return false;
    if ( g_pywifide__valid_funcs( $func ) ) return true;
    return false;
  } );
  $rs = $sandbox->execute( $script );
  $g_results .= $rs;
  $rs = ob_get_clean();
  //echo "\nCLN: ", $rs, "\n";
  $g_results .= $rs;
}

function g_pywifide_init( $suite_code, $username, $password ) {
  global $g_token, $g_suite_id, $g_testor_dir, $g_suite_code, $g_testor_username, $g_testor_password, $g_last_version, $g_src_dir, $g_clear_version;
  $g_testor_dir = __DIR__;
  $g_suite_code = $suite_code;
  $g_testor_username = $username;
  $g_testor_password = $password;
  $g_last_version = 0;
  $g_src_dir = '';
  $g_clear_version = false;
  $g_token = '_';
  $g_suite_id = -1;
}

function g_pywifide_vars() {
  global $g_token, $g_suite_id, $g_testor_dir, $g_suite_code, $g_testor_username, $g_testor_password, $g_last_version, $g_src_dir, $g_clear_version;
  return [ $g_token, $g_suite_code, $g_suite_id ];
}

function g_pywifide_param( $key ) {
  if ( isset( $_POST[ $key ] ) ) return $_POST[ $key ];
  if ( isset( $_GET[ $key ] ) ) return $_GET[ $key ];
  return '';
}

header('Content-Type: text/plain');

$g_results = "\n";

$token = g_pywifide_param('token');
if ( !isset( $_SESSION['pyWifide_'.$token] ) || $_SESSION['pyWifide_'.$token] === false ) {
  echo $g_results;
  exit();
}


$g_buffer_dir = __DIR__ . '/buffers';
@mkdir( $g_buffer_dir, 0777, true );

$g_work_dir = './';
$g_load_text = '';
$g_download_text = '';
$g_source_text = '';
$g_list_text = '';
$g_remove_text = '';
$g_workdir_text = '';
$g_shp_text = '';

$sql = g_pywifide_param('s');
$sql = g_pywifide_refine( $sql );
if ( strpos( $g_load_text, "\n# loading #\n" ) !== false ) {
  echo $g_load_text;
  exit;
}
if ( strpos( $sql, "\n# loadsrc #\n" ) !== false ) {
  $cat = "[SRC]" . "\n" . $g_source_text;
  $rs = '';
  g_pywifide_parse_results( $cat, $rs );
  $g_results = $g_results . "\n" . trim( $rs ) . "\n";
}
if ( strpos( $sql, "\n# loadcat #\n" ) !== false ||  strpos( $sql, "\n# loadsave #\n" ) !== false ) {
  if ( strpos( $sql, "\n# rawsrc #\n" ) !== false ) {
    $cat = "[Python] Start                                        ";
    $rs = '';
    g_pywifide_parse_results( $cat, $rs );
    $g_results .= "\n" . trim( $rs ) . "\n";
    $g_results .= $sql;
    $cat = "[Python] End                                          ";
    $rs = '';
    g_pywifide_parse_results( $cat, $rs );
    $g_results .= "\n" . trim( $rs ) . "\n";
  } else {
    $cat = "[Python]" . "\n" . $sql;
    $rs = '';
    g_pywifide_parse_results( $cat, $rs );
    $g_results .= "\n" . trim( $rs ) . "\n";
  }
} else {
  g_pywifide__python_exec( $sql );
}
if ( $g_results === null ) {
  $g_results = '';
}
if ( strpos( "\n" . $sql . "\n", "\n# shp #\n" ) !== false ) {
  if ( strlen( $g_shp_text ) > 0 ) {
    if ( strpos( "\n" . $g_shp_text . "\n", "\n// loadshp //\n" ) !== false ) {
      echo "\n", "=====] SHP [=====", "\n", str_replace( "\n// loadshp //\n", '', $g_shp_text), "\n", "====================", "\n";
    }
  }
}
if ( strpos( "\n" . $sql . "\n", "pytestor.api_testor_startup(" ) !== false &&  strpos( "\n" . $sql . "\n", "pytestor.api_testor_shutdown(" ) !== false ) {
  if ( strpos( $g_results, "| GREEN" ) !== false || strpos( $g_results, "| RED" ) !== false ) {
    if ( strpos( "\n" . $sql . "\n", 'g_suite_code = ' ) !== false ) {
      $idx = strpos( "\n" . $sql . "\n", 'g_suite_code = ' );
      $tmp = substr( $sql, $idx + 15 );
      $idx = strpos( $tmp, ';' );
      if ( $idx !== false ) {
        $tmp = substr( $tmp, 0, $idx );
      }
      $idx = strpos( $tmp, "\n" );
      if ( $idx !== false ) {
        $tmp = substr( $tmp, 0, $idx );
      }
      $tmp = trim( $tmp );
      if ( $tmp[0] === "'" ) {
        $tmp = substr( $tmp, 1 );
      }
      if ( $tmp[strlen($tmp) - 1] === "'" ) {
        $tmp = substr( $tmp, 0, strlen( $tmp ) - 1 );
      }
      $tmp = trim( $tmp );
      if ( $tmp[0] === '"' ) {
        $tmp = substr( $tmp, 1 );
      }
      if ( $tmp[strlen($tmp) - 1] === '"' ) {
        $tmp = substr( $tmp, 0, strlen( $tmp ) - 1 );
      }
      $suite_code = $tmp;
      $suite_code = str_replace( "\n", '', $suite_code );
      $suite_code = str_replace( "\r", '', $suite_code );
      $suite_code = str_replace( '"', '', $suite_code );
      $suite_code = str_replace( '\\', '', $suite_code );
      if ( $suite_code !== '_' && $suite_code !== '' ) {
        require_once __DIR__ . '/svc.php';
        g_svc( $suite_code, $g_work_dir );
      }
    }
  }
}

if ( strpos( $sql, "\n# loadhelp #\n" ) !== false ) {
  $g_results = "\n" . trim( g_pywifide_help() ) . "\n" . $g_results;
}
if ( strpos( $sql, "\n# loadlist #\n" ) !== false ) {
  $g_results = $g_results . "\n" . trim( $g_list_text ) . "\n";
}
if ( strpos( $sql, "\n# loadremove #\n" ) !== false ) {
  $g_results = $g_results . "\n" . trim( $g_remove_text ) . "\n";
}
if ( strpos( $sql, "\n# loaddownload #\n" ) !== false ) {
  $g_results = $g_results . "\n" . trim( $g_download_text ) . "\n";
}
if ( strpos( $sql, "\n# loadworkdir #\n" ) !== false ) {
  $g_results = $g_results . "\n" . trim( $g_workdir_text ) . "\n";
}
if ( strpos( $sql, "\n# spy #\n" ) !== false ) {
  echo "\n", "=====] SPY [=====", "\n", $sql, "\n", "====================", "\n";
}
echo $g_results;
?>