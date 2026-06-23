#
# Copyright (c) 2026 Dinh Thoai Tran <zinospetrel@sdf.org>
# All rights reserved.
#
# + Source URL: https://github.com/progorker/pgk_pytestor/
#
# + License: GPL-2.0
#

import sys
import subprocess
import pytestor_cfg
import traceback

def g_mytestor_exec( sql ):
  host = pytestor_cfg.mytestor_host
  port = pytestor_cfg.mytestor_port
  user = pytestor_cfg.mytestor_username
  passw = pytestor_cfg.mytestor_password
  db = pytestor_cfg.mytestor_database

  cmd = pytestor_cfg.mytestor_command
  if cmd.find( 'mariadb' ) >= 0:
    cmd += " --skip-ssl-verify-server-cert"
  else:
    cmd += " --ssl-mode=DISABLED"

  text = subprocess.run(f"{cmd} --disable-auto-rehash -h {host} -P {port} --user={user} --password={passw} -e \"use {db}; {sql} \" ", shell=True, capture_output=True, text=True )
  return text.stdout

def g_escape( sql ):
  sql = sql.replace( "_", "_._us_._" )
  sql = sql.replace( "\n", "__nl__" )
  sql = sql.replace( "\r", "__cr__" )
  sql = sql.replace( "\t", "__tb__" )
  sql = sql.replace( "\\", "__sl__" )
  sql = sql.replace( '"', "__dq__" )
  sql = sql.replace( "'", "__sq__" )
  sql = sql.replace( "`", "__td__" )
  return sql

def g_unescape( sql ):
  sql = sql.replace( "__nl__", "\n" )
  sql = sql.replace( "__cr__", "\r" )
  sql = sql.replace( "__tb__", "\t" )
  sql = sql.replace( "__sl__", "\\" )
  sql = sql.replace( "__dq__", '"' )
  sql = sql.replace( "__sq__", "'" )
  sql = sql.replace( "__td__", "`" )
  sql = sql.replace( "_._us_._", "_" )
  return sql

def g_fill_table( cols, rows, fsz, p_results ):
  p_results = p_results +"\n\n";
  p_results = p_results + '+';
  for i in range( len( cols ) ):
    p_results = p_results + "-".ljust( fsz[ i ] + 2, '-' ) + '+'
  p_results = p_results + "\n"
  p_results = p_results + '|'
  for i in range( len( cols ) ):
    p_results = p_results + (" " + cols[ i ] + " ").ljust( fsz[ i ] + 2, ' ' ) + '|'
  p_results = p_results + "\n"
  p_results = p_results + '+'
  for i in range( len( cols ) ):
    p_results = p_results + "-".ljust( fsz[ i ] + 2, '-' ) + '+'
  p_results = p_results + "\n";
  for j in range( len( rows ) ):
    rw = rows[ j ]
    p_results = p_results + '|'
    for i in range( len( rw ) ):
      p_results = p_results + (" " + rw[ i ] + " ").ljust( fsz[ i ] + 2, ' ' ) + '|'
    p_results = p_results + "\n"
  p_results = p_results + '+'
  for i in range( len( cols ) ):
    p_results = p_results + "-".ljust( fsz[ i ] + 2, '-' ) + '+'
  p_results = p_results + "\n"
  return p_results

def g_parse_results( text ):
  lines = text.split( "\n" )
  fld_cnt = -1;
  cols = []
  rows = []
  fsz = [];
  p_results = ''
  for ln in lines:
    ln = ln.strip()
    if ln == '':
      continue
    fields = ln.split( "\t" )
    if len( fields ) != fld_cnt:
      if fld_cnt > 0:
        p_results = g_fill_table( cols, rows, fsz, p_results )
      rows = []
      cols = []
      fsz = []
      for fd in fields:
        cols.append( fd )
        fsz.append( len( fd ) )
      fld_cnt = len( fields )
      continue
    rw = []
    for i in range( len( fields ) ):
      fd = fields[ i ]
      sz = len( fd )
      if sz > fsz[ i ]:
        fsz[ i ] = sz
      rw.append( fd )
    rows.append( rw )
  if fld_cnt > 0:
    p_results = g_fill_table( cols, rows, fsz, p_results )
  return p_results

def g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, p_proc ):
  proc_list = ['api_testor_equals', 'api_testor_not_equals', 'api_testor_greater_than', 'api_testor_not_greater_than', 'api_testor_less_than', 'api_testor_not_less_than' ]
  if p_proc not in proc_list:
    return -1
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_id = int( str(p_case_id) + '' )
  code = g_escape( p_code )
  operand = float( str(p_operand) + '' )
  value = float( str(p_value) + '' )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_id = {case_id}; set @v_code = api_testor_unescape('{code}'); set @v_operand = {operand}; set @v_value = {value}; set @v_id = -1; call {p_proc}( @v_token, @v_id, @v_suite_id, @v_case_id, @v_code, @v_operand, @v_value ); select @v_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_id = int( str(ln) + '' )
  return p_id

def g_testing_string( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, p_proc ):
  proc_list = ['api_testor_same', 'api_testor_not_same', 'api_testor_contains', 'api_testor_not_contains']
  if p_proc not in proc_list:
    return -1
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_id = int( str(p_case_id) + '' )
  code = g_escape( p_code )
  operand = g_escape( p_operand );
  value = g_escape( p_value );
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_id = {case_id}; set @v_code = api_testor_unescape('{code}'); set @v_operand = api_testor_unescape('{operand}'); set @v_value = api_testor_unescape('{value}'); set @v_id = -1; call {p_proc}( @v_token, @v_id, @v_suite_id, @v_case_id, @v_code, @v_operand, @v_value ); select @v_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_id = int( str(ln) + '' )
  return p_id


# Table: 1.
def api_testor_welcome():
  # return p_results
  sql = "select message from testor_welcome order by id asc;"
  text = g_mytestor_exec( sql )
  results = g_parse_results( text )
  return results

# Function: 1.
def api_testor_is_online( p_token ):
  # return p_online
  token = g_escape( p_token )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_online = api_testor_is_online( @v_token ); select @v_online;"
  text = g_mytestor_exec( sql );
  lines = text.split( "\n" )
  rs = lines[1].strip()
  if rs == '1':
    return True
  return False

# Function: 2.
def api_testor_has_right( p_token, p_right_code ):
  # return p_right
  token = g_escape( p_token )
  right_code = g_escape( p_right_code )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_right_code = api_testor_unescape('{right_code}'); set @v_right = api_testor_has_right( @v_token, @v_right_code ); select @v_right;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  rs = lines[1].strip()
  if rs == '1':
    return True
  return False

# Function: 3.
def api_testor_escape( p_input ):
  # return p_output
  return g_escape( p_input )

# Function: 4.
def api_testor_unescape( p_input ):
  # return p_output
  return g_unescape( p_input )

# Procedure: 1.
def api_testor_login( p_username, p_password ):
  # return p_token
  p_token = ''
  username = g_escape( p_username )
  password = g_escape( p_password )
  sql = f"set @v_token = '_'; set @v_username = api_testor_unescape('{username}'); set @v_password = api_testor_unescape('{password}'); call api_testor_login( @v_token, @v_username, @v_password); select @v_token;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  p_token = lines[1].strip()
  return p_token

# Procedure 2.
def api_testor_logout( p_token ):
  # no return
  token = g_escape( p_token )
  sql = f"set @v_token = api_testor_unescape('{token}'); call api_testor_logout( @v_token );"
  g_mytestor_exec( sql )

# Procedure: 3.
def api_testor_current_user( p_token ):
  # return p_user_id, p_username
  token = g_escape( p_token )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_user_id = -1; set @v_username = '_'; call api_testor_current_user( @v_token, @v_user_id, @v_username ); select @v_user_id, @v_username;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[ 1 ].strip()
  fields = ln.split( "\t" )
  p_user_id = int( fields[0] )
  p_username = fields[1].strip()
  return p_user_id, p_username

# Procedure: 4.
def api_testor_user_rights( p_token ):
  # return p_api_call, p_user_make, p_user_demo, p_storage_full
  token = g_escape( p_token )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_api_call = -1; set @v_user_make = -1; set @v_user_demo = -1; set @v_storage_full = -1; call api_testor_user_rights( @v_token, @v_api_call, @v_user_make, @v_user_demo, @v_storage_full ); select @v_api_call, @v_user_make, @v_user_demo, @v_storage_full;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  fields = ln.split( "\t" )
  p_api_call = True if fields[0] == '1' else False
  p_user_make = True if fields[1] == '1' else False
  p_user_demo = True if fields[2] == '1' else False
  p_storage_full = True if fields[3] == '1' else False
  return p_api_call, p_user_make, p_user_demo, p_storage_full

# Procedure 5.
def api_testor_change_password( p_token, p_password ):
  # no return
  token = g_escape( p_token )
  password = g_escape( p_password )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_password = api_testor_unescape('{password}'); call api_testor_change_password( @v_token, @v_password );"
  g_mytestor_exec( sql )

# Procedure 6.
def api_testor_create_user( p_token, p_username, p_password, p_api_call, p_user_make, p_user_demo, p_quota ):
  # no return
  token = g_escape( p_token )
  username = g_escape( p_username )
  password = g_escape( p_password )
  api_call = 1 if p_api_call else 0
  user_make = 1 if p_user_make else 0
  user_demo = 1 if p_user_demo else 0
  quota = int( str(p_quota) )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_username = api_testor_unescape('{username}'); set @v_password = api_testor_unescape('{password}'); set @v_api_call = {api_call}; set @v_user_make = {user_make}; set @v_user_demo = {user_demo}; set @v_quota = {quota}; call api_testor_create_user( @v_token, @v_username, @v_password, @v_api_call, @v_user_make, @v_user_demo, @v_quota );"
  g_mytestor_exec( sql )

# Procedure: 7.
def api_testor_suite( p_token, p_suite_code ):
  # return p_suite_id
  token = g_escape( p_token )
  suite_code = g_escape( p_suite_code )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_code = api_testor_unescape('{suite_code}'); set @v_suite_id = -1; call api_testor_suite( @v_token, @v_suite_id, @v_suite_code ); select @v_suite_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_suite_id = int( ln )
  return p_suite_id

# Procedure: 8.
def api_testor_case( p_token, p_suite_id, p_case_code ):
  # return p_case_id
  token = g_escape( p_token )
  case_code = g_escape( p_case_code )
  suite_id = int( str(p_suite_id) + '' )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_case_code = api_testor_unescape('{case_code}'); set @v_suite_id = {suite_id}; set @v_case_id = -1; call api_testor_case( @v_token, @v_case_id, @v_suite_id, @v_case_code ); select @v_case_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_case_id = int( ln )
  return p_case_id

# Procedure: 9.
def api_testor_suite_case( p_token, p_suite_code, p_case_code ):
  # return p_suite_id, p_case_id
  token = g_escape( p_token )
  suite_code = g_escape( p_suite_code )
  case_code = g_escape( p_case_code )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_code = api_testor_unescape('{suite_code}'); set @v_case_code = api_testor_unescape('{case_code}'); set @v_suite_id = -1; set @v_case_id = -1; call api_testor_suite_case( @v_token, @v_suite_id, @v_case_id, @v_suite_code, @v_case_code ); select @v_suite_id, @v_case_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  fields = ln.split( "\t" )
  p_suite_id = int( fields[0].strip() )
  p_case_id = int( fields[1].strip() )
  return p_suite_id, p_case_id

# Procedure: 10.
def api_testor_clean( p_token, p_suite_id ):
  # no return
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; call api_testor_clean( @v_token, @v_suite_id );"
  g_mytestor_exec( sql )

# Procedure: 11.
def api_testor_test( p_token, p_suite_id, p_case_id, p_test_code, p_condition, p_message ):
  # return p_test_id
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_id = int( str(p_case_id) + '' )
  test_code = g_escape( p_test_code )
  condition = 1 if p_condition else 0
  message = g_escape( p_message )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_id = {case_id}; set @v_test_code = api_testor_unescape('{test_code}'); set @v_condition = {condition}; set @v_message = api_testor_unescape('{message}'); set @v_test_id = -1; call api_testor_test( @v_token, @v_test_id, @v_suite_id, @v_case_id, @v_test_code, @v_condition, @v_message ); select @v_test_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_test_id = int( str(ln) + '' )
  return p_test_id

# Procedure: 12.
def api_testor_finish( p_token, p_suite_id, p_beauty = False ):
  # return p_results
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  if p_beauty:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; call api_testor_finish( @v_token, @v_suite_id )\\G"
    p_results = g_mytestor_exec( sql )
  else:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; call api_testor_finish( @v_token, @v_suite_id );"
    text = g_mytestor_exec( sql )
    p_results = g_parse_results( text )
  return p_results

# Procedure: 13.
def api_testor_result( p_token, p_suite_id, p_beauty = False ):
  # return p_results
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  if p_beauty:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; call api_testor_result( @v_token, @v_suite_id )\\G"
    p_results = g_mytestor_exec( sql )
  else:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; call api_testor_result( @v_token, @v_suite_id );"
    text = g_mytestor_exec( sql )
    p_results = g_parse_results( text )
  return p_results

# Procedure: 14.
def api_testor_option( p_token, p_suite_id, p_data, p_code, p_remove ):
  # return p_data
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  code = g_escape( p_code )
  remove = 1 if p_remove else 0
  if p_data is None:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_code = api_testor_unescape('{code}'); set @v_remove = {remove}; set @v_data = NULL; call api_testor_option( @v_token, @v_suite_id, @v_data, @v_code, @v_remove ); select @v_data;"
  else:
    data = g_escape( p_data )
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_code = api_testor_unescape('{code}'); set @v_remove = {remove}; set @v_data = api_testor_unescape('{data}'); call api_testor_option( @v_token, @v_suite_id, @v_data, @v_code, @v_remove ); select @v_data;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1]
  p_data = ln
  return p_data

# Procedure: 15.
def api_testor_e_functions( p_mysql_database, p_find ):
  # return p_names
  mysql_database = g_escape( p_mysql_database )
  mysql_find = g_escape( p_find )
  sql = f"set @v_mysql_database = api_testor_unescape('{mysql_database}'); set @v_find = api_testor_unescape('{mysql_find}'); set @v_names = ''; call api_testor_e_functions( @v_mysql_database, @v_find, @v_names); select @v_names;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1]
  p_names = ln
  return p_names

# Procedure: 16.
def api_testor_e_procedures( p_mysql_database, p_find ):
  # return p_names
  mysql_database = g_escape( p_mysql_database )
  mysql_find = g_escape( p_find )
  sql = f"set @v_mysql_database = api_testor_unescape('{mysql_database}'); set @v_find = api_testor_unescape('{mysql_find}'); set @v_names = ''; call api_testor_e_procedures( @v_mysql_database, @v_find, @v_names); select @v_names;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1]
  p_names = ln
  return p_names

# Procedure: 17.
def api_testor_e_tables( p_mysql_database, p_find ):
  # return p_names
  mysql_database = g_escape( p_mysql_database )
  mysql_find = g_escape( p_find )
  sql = f"set @v_mysql_database = api_testor_unescape('{mysql_database}'); set @v_find = api_testor_unescape('{mysql_find}'); set @v_names = ''; call api_testor_e_tables( @v_mysql_database, @v_find, @v_names); select @v_names;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1]
  p_names = ln
  return p_names

# Procedure: 18.
def api_testor_version( p_token, p_suite_id, p_cur_ver ):
  # no return
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  cur_ver = int( str(p_cur_ver) + '' )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_cur_ver = {cur_ver}; call api_testor_version( @v_token, @v_suite_id, @v_cur_ver );"
  g_mytestor_exec( sql )

# Procedure: 19.
def api_testor_source( p_token, p_suite_id, p_case_code, p_beauty = False ):
  # return p_results
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_code = g_escape( p_case_code )
  if p_beauty:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_code = api_testor_unescape('{case_code}'); call api_testor_source( @v_token, @v_suite_id, @v_case_code )\\G"
    p_results = g_mytestor_exec( sql )
  else:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_code = api_testor_unescape('{case_code}'); call api_testor_source( @v_token, @v_suite_id, @v_case_code );"
    text = g_mytestor_exec( sql )
    p_results = g_parse_results( text )
  return p_results

# Procedure: 20.
def api_testor_source_list( p_token, p_suite_id, p_page_no, p_beauty = False ):
  # return p_results
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  page_no = int( str(p_page_no) + '' )
  if p_beauty:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_page_no = {page_no}; call api_testor_source_list( @v_token, @v_suite_id, @v_page_no )\\G"
    p_results = g_mytestor_exec( sql )
  else:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_page_no = {page_no}; call api_testor_source_list( @v_token, @v_suite_id, @v_page_no );"
    text = g_mytestor_exec( sql )
    p_results = g_parse_results( text )
  return p_results

# Procedure: 21.
def api_testor_true( p_token, p_suite_id, p_case_id, p_test_code, p_condition ):
  # return p_test_id
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_id = int( str(p_case_id) + '' )
  test_code = g_escape( p_test_code )
  condition = 1 if p_condition else 0
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_id = {case_id}; set @v_test_code = api_testor_unescape('{test_code}'); set @v_condition = {condition}; set @v_test_id = -1; call api_testor_true( @v_token, @v_test_id, @v_suite_id, @v_case_id, @v_test_code, @v_condition ); select @v_test_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_test_id = int( str(ln) + '' )
  return p_test_id

# Procedure: 22.
def api_testor_not_true( p_token, p_suite_id, p_case_id, p_test_code, p_condition ):
  # return p_test_id
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_id = int( str(p_case_id) + '' )
  test_code = g_escape( p_test_code )
  condition = 1 if p_condition else 0
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_id = {case_id}; set @v_test_code = api_testor_unescape('{test_code}'); set @v_condition = {condition}; set @v_test_id = -1; call api_testor_not_true( @v_token, @v_test_id, @v_suite_id, @v_case_id, @v_test_code, @v_condition ); select @v_test_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_test_id = int( str(ln) + '' )
  return p_test_id

# Procedure: 23.
def api_testor_success( p_token, p_suite_id, p_page_no, p_beauty = False ):
  # return p_results
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  page_no = int( str(p_page_no) + '' )
  if p_beauty:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_page_no = {page_no}; call api_testor_success( @v_token, @v_suite_id, @v_page_no )\\G"
    p_results = g_mytestor_exec( sql )
  else:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_page_no = {page_no}; call api_testor_success( @v_token, @v_suite_id, @v_page_no );"
    text = g_mytestor_exec( sql )
    p_results = g_parse_results( text )
  return p_results

# Procedure: 24.
def api_testor_error( p_token, p_suite_id, p_case_id, p_test_code, p_message ):
  # return p_test_id
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  case_id = int( str(p_case_id) + '' )
  test_code = g_escape( p_test_code )
  message = g_escape( p_message )
  sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_case_id = {case_id}; set @v_test_code = api_testor_unescape('{test_code}'); set @v_message = api_testor_unescape('{message}'); set @v_test_id = -1; call api_testor_error( @v_token, @v_test_id, @v_suite_id, @v_case_id, @v_test_code, @v_message ); select @v_test_id;"
  text = g_mytestor_exec( sql )
  lines = text.split( "\n" )
  ln = lines[1].strip()
  p_test_id = int( str(ln) + '' )
  return p_test_id

# Procedure: 25.
def api_testor_equals( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_equals'
  return g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 26.
def api_testor_not_equals( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_not_equals'
  return g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 27.
def api_testor_greater_than( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_greater_than'
  return g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 28.
def api_testor_not_greater_than( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_not_greater_than'
  return g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 29.
def api_testor_less_than( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_less_than'
  return g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 30.
def api_testor_not_less_than( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_not_less_than'
  return g_testing_double( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 31.
def api_testor_same( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_same'
  return g_testing_string( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 32.
def api_testor_not_same( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_not_same'
  return g_testing_string( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 33.
def api_testor_contains( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_contains'
  return g_testing_string( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 34.
def api_testor_not_contains( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value ):
  # return p_test_id
  proc = 'api_testor_not_contains'
  return g_testing_string( p_token, p_suite_id, p_case_id, p_code, p_operand, p_value, proc )

# Procedure: 35.
def api_testor_failed( p_token, p_suite_id, p_page_no, p_beauty = False ):
  # return p_results
  token = g_escape( p_token )
  suite_id = int( str(p_suite_id) + '' )
  page_no = int( str(p_page_no) + '' )
  if p_beauty:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_page_no = {page_no}; call api_testor_failed( @v_token, @v_suite_id, @v_page_no )\\G"
    p_results = g_mytestor_exec( sql )
  else:
    sql = f"set @v_token = api_testor_unescape('{token}'); set @v_suite_id = {suite_id}; set @v_page_no = {page_no}; call api_testor_failed( @v_token, @v_suite_id, @v_page_no );"
    text = g_mytestor_exec( sql )
    g_results = g_parse_results( text )
  return g_results

# Procedure: 36.
def api_testor_man( p_module, p_kind, p_code ):
  # return p_man
  module = g_escape( p_module )
  kind = g_escape( p_kind )
  code = g_escape( p_code )
  sql = f"set @v_module = api_testor_unescape('{module}'); set @v_kind = api_testor_unescape('{kind}'); set @v_code = api_testor_unescape('{code}'); set @v_man = ''; call api_testor_man( @v_module, @v_kind, @v_code, @v_man ); select @v_man as manual\\G"
  p_man = g_mytestor_exec( sql )
  idx = p_man.find( 'manual:' )
  if idx >= 0:
    p_man = p_man[idx+7:]
    p_man = p_man.strip()
  return p_man

# Procedure: 37.
def api_testor_pattern( p_module, p_kind, p_code, p_variant ):
  # return p_pattern
  module = g_escape( p_module )
  kind = g_escape( p_kind )
  code = g_escape( p_code )
  variant = g_escape( p_variant )
  sql = f"set @v_module = api_testor_unescape('{module}'); set @v_kind = api_testor_unescape('{kind}'); set @v_code = api_testor_unescape('{code}'); set @v_variant = api_testor_unescape('{variant}'); set @v_pattern = ''; call api_testor_pattern( @v_module, @v_kind, @v_code, @v_variant, @v_pattern ); select @v_pattern as pattern\\G"
  p_pattern = g_mytestor_exec( sql )
  idx = p_pattern.find( 'pattern:' )
  if idx >= 0:
    p_pattern = p_pattern[idx+8:]
    p_pattern = p_pattern.strip()
  return p_pattern

# Procedure: 38.
def api_testor_startup():
  # no return
  global g_testor_dir, g_suite_code, g_testor_username, g_testor_password, g_token, g_src_dir, g_suite_id, g_last_version, g_clear_version

  g_token = api_testor_login( g_testor_username, g_testor_password )
  g_suite_id = api_testor_suite( g_token, g_suite_code )
  api_testor_clean( g_token, g_suite_id )


  def g_exception_handler( exctype, value, p_traceback):
    global g_token, g_suite_id
    message = "Exception: " + str(value) + "\n" + "".join(traceback.format_exception(value))
    case_id = api_testor_case( g_token, g_suite_id, 'global_error' )
    test_id = api_testor_error( g_token, g_suite_id, case_id, 'exception.1', message )
    api_testor_shutdown()

  sys.excepthook = g_exception_handler

  if g_clear_version:
    data = None
    data = api_testor_option( g_token, g_suite_id, data, 'ver:cur', True )

  api_testor_version( g_token, g_suite_id, g_last_version )
  data = api_testor_option( g_token, g_suite_id, g_src_dir, 'src_dir', False )

# Procedure: 38.
def api_testor_shutdown():
  # no return
  global g_token, g_suite_id

  results = api_testor_finish( g_token, g_suite_id, False )
  print( "\n========== Results: Finished ==========\n" + results + "\n" )

  results = api_testor_source_list( g_token, g_suite_id, 1, False )
  print( "\n========== Results: Source List ==========\n" + results +"\n" )

  results = api_testor_success( g_token, g_suite_id, 1, False )
  print( "\n========== Results: Success ==========\n" + results + "\n" )

  results = api_testor_failed( g_token, g_suite_id, 1, False )
  print( "\n========== Results: Failed ==========\n" + results + "\n" )

  results = api_testor_result( g_token, g_suite_id, False )
  print( "\n========== Results: Result ==========\n" + results + "\n" )

  api_testor_logout( g_token )

