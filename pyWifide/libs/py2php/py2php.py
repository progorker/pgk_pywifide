import ast
import sys
import os
from pprint import pprint

def g_ast_constant( node, indent = "" ):
    php_scrp = ""
    type_name = type( node.value ).__name__
    val = '""'
    if type_name == "int":
        val = str(node.value)
    elif type_name == "float":
        val = str(node.value)
    elif type_name == "str":
        val = str(node.value)
        val = val.replace("\n", "\\" + "n")
        val = val.replace("\r", "\\" + "r")
        val = val.replace("\t", "\\" + "t")
        val = f'"{val}"'
    elif type_name == "bool":
        val = str(node.value).lower()
    else:
        val = str(node.value)
        if val == "None":
            val = "null"
    php_scrp += val
    return php_scrp

def g_ast_assign( node, indent = "" ):
    php_scrp = ""
    for target in node.targets:
       val = g_vst_node( node.value, False )
       val = val.strip()
       key = g_vst_node( target )

       key = key.replace('__tuple__s__', '[ ')
       key = key.replace('__tuple__e__', ' ]')
       val = val.replace('__tuple__s__', 'g_pywifide_tuple( ')
       val = val.replace('__tuple__e__', ' )')

       php_scrp += f"{indent}{key} = {val};\n"

    if php_scrp.find( " = " ) >= 0:
       if php_scrp.find( "g_pywifide_api_" ) >= 0:
          idx = php_scrp.find( " = " )
          php_scrp = "[ " + php_scrp[:idx] + " ]" + php_scrp[idx:] 

    return php_scrp

def g_ast_aug_assign( node, indent = "" ):
    php_scrp = ""
    val = g_vst_node( node.value, False )
    val = val.strip()
    key = g_vst_node( node.target )
    op = g_vst_node( node.op )

    key = key.replace('__tuple__s__', '[ ')
    key = key.replace('__tuple__e__', ' ]')
    val = val.replace('__tuple__s__', 'g_pywifide_tuple( ')
    val = val.replace('__tuple__e__', ' )')

    php_scrp += f"{indent}{key} {op}= {val};\n"
    return php_scrp

def g_ast_compare( node, indent = "" ):
    php_scrp = ""
    left = node.left
    php_scrp += g_vst_node( node.left ) + " "
    php_scrp += g_vst_node( node.ops[0] ) + " "
    php_scrp += g_vst_node( node.comparators[0] )
    return php_scrp

def g_ast_bin_op( node, indent = "" ):
    php_scrp = ""
    left = node.left
    php_scrp += "( " + g_vst_node( node.left ) + " "
    php_scrp += g_vst_node( node.op ) + " "
    php_scrp += g_vst_node( node.right ) + " )"
    if php_scrp.find( "__floordiv__" ) > 0:
        php_scrp = "g_pywifide_floor" + php_scrp.replace( "__floordiv__", "/" )
    return php_scrp

def g_ast_name( node, indent = "" ):
    key = str(node.id)
    return f"${key}"

def g_ast_compare_ops_is( node, indent = "" ):
    return "is"

def g_ast_compare_ops_is_not( node, indent = "" ):
    return "is not"

def g_ast_compare_ops_less( node, indent = "" ):
    return "<"

def g_ast_compare_ops_less_eq( node, indent = "" ):
    return "<="

def g_ast_compare_ops_greater( node, indent = "" ):
    return ">"

def g_ast_compare_ops_greater_eq( node, indent = "" ):
    return ">="

def g_ast_compare_ops_equals( node, indent = "" ):
    return "=="

def g_ast_compare_ops_not_equals( node, indent = "" ):
    return "!="

def g_ast_compare_ops_in( node, indent = "" ):
    return "in"

def g_ast_compare_ops_not_in( node, indent = "" ):
    return "not in"

def g_ast_if( node, closed = True, indent = "" ):
    php_scrp = ""
    php_scrp += f"{indent}if (" + g_vst_node( node.test, False, indent ) + ") {\n"
    for node_bd in node.body:
        php_scrp += g_vst_node( node_bd, False, indent + "  " )
    if node.orelse is not None:
        for node_el in node.orelse:
            php_scrp += f"{indent}" + "} else " + g_ast_if( node_el, False, indent )
    if closed:
        php_scrp += "}\n"
    return php_scrp

def g_ast_while( node, indent = "" ):
    php_scrp = ""
    php_scrp += f"{indent}while (" + g_vst_node( node.test, False, indent ) + ") {\n"
    for node_bd in node.body:
        php_scrp += g_vst_node( node_bd, False, indent + "  " )
    php_scrp += f"{indent}" + "}\n"
    return php_scrp

def g_ast_list( node, indent = "" ):
    php_scrp = ""
    text = ""
    for node_bd in node.elts:
        if text != "":
            text += ", "
        text += g_vst_node( node_bd, False, indent )
    php_scrp += "[ " + text + " ]"
    return php_scrp

def g_ast_add( node, indent = "" ):
    return "+"

def g_ast_sub( node, indent = "" ):
    return "-"

def g_ast_mult( node, indent = "" ):
    return "*"

def g_ast_div( node, indent = "" ):
    return "/"

def g_ast_floor_div( node, indent = "" ):
    return "__floordiv__"

def g_ast_pow( node, indent = "" ):
    return "**"

def g_ast_expr( node, indent = "" ):
    php_scrp = ""
    php_scrp += g_vst_node( node.value, False, indent )
    return php_scrp

def g_ast_subscript( node, indent = "" ):
    php_scrp = ""
    php_scrp += g_vst_node( node.value ) + "[ "
    php_scrp += g_vst_node( node.slice ) + " ]"
    return php_scrp

def g_ast_attribute( node, indent = "" ):
    php_scrp = ""
    php_scrp += g_vst_node( node.value ) + "."
    type_name = type( node.attr ).__name__
    if type_name == "str":
        php_scrp += str(node.attr)
    else:
        php_scrp += g_vst_node( node.attr )
    return php_scrp


def g_ast_call( node, indent = "" ):
    php_scrp = f"{indent}"

    func_name = g_vst_node( node.func )
    if isinstance( node.func, ast.Name ):
        func_name = str( node.func.id )

    supported_tag = [\
"g_pywifide_str",\
"g_pywifide_bytearray",\
"g_pywifide_bool",\
"g_pywifide_bin",\
"g_pywifide_any",\
"g_pywifide_all",\
"g_pywifide_abs",\
"g_pywifide_print",\
"g_pywifide_range",\
"g_pywifide_tcexec",\
"g_pywifide_init",\
"g_pywifide_vars",\
"g_pywifide_api_testor_escape",\
"g_pywifide_api_testor_has_right",\
"g_pywifide_api_testor_is_online",\
"g_pywifide_api_testor_unescape",\
"g_pywifide_api_testor_welcome",\
"g_pywifide_api_testor_case",\
"g_pywifide_api_testor_change_password",\
"g_pywifide_api_testor_clean",\
"g_pywifide_api_testor_contains",\
"g_pywifide_api_testor_create_user",\
"g_pywifide_api_testor_current_user",\
"g_pywifide_api_testor_e_functions",\
"g_pywifide_api_testor_e_procedures",\
"g_pywifide_api_testor_equals",\
"g_pywifide_api_testor_error",\
"g_pywifide_api_testor_e_tables",\
"g_pywifide_api_testor_failed",\
"g_pywifide_api_testor_finish",\
"g_pywifide_api_testor_greater_than",\
"g_pywifide_api_testor_less_than",\
"g_pywifide_api_testor_login",\
"g_pywifide_api_testor_logout",\
"g_pywifide_api_testor_man",\
"g_pywifide_api_testor_not_contains",\
"g_pywifide_api_testor_not_equals",\
"g_pywifide_api_testor_not_greater_than",\
"g_pywifide_api_testor_not_less_than",\
"g_pywifide_api_testor_not_same",\
"g_pywifide_api_testor_not_true",\
"g_pywifide_api_testor_option",\
"g_pywifide_api_testor_pattern",\
"g_pywifide_api_testor_result",\
"g_pywifide_api_testor_same",\
"g_pywifide_api_testor_shutdown",\
"g_pywifide_api_testor_source_list",\
"g_pywifide_api_testor_source",\
"g_pywifide_api_testor_startup",\
"g_pywifide_api_testor_success",\
"g_pywifide_api_testor_suite_case",\
"g_pywifide_api_testor_suite",\
"g_pywifide_api_testor_test",\
"g_pywifide_api_testor_true",\
"g_pywifide_api_testor_user_rights",\
"g_pywifide_api_testor_version"\
]

    supported_src = [\
"str",\
"bytearray",\
"bool",\
"bin",\
"any",\
"all",\
"abs",\
"print",\
"range",\
"g_pywifide_tcexec",\
"g_pywifide_init",\
"g_pywifide_vars",\
"$pytestor.api_testor_escape",\
"$pytestor.api_testor_has_right",\
"$pytestor.api_testor_is_online",\
"$pytestor.api_testor_unescape",\
"$pytestor.api_testor_welcome",\
"$pytestor.api_testor_case",\
"$pytestor.api_testor_change_password",\
"$pytestor.api_testor_clean",\
"$pytestor.api_testor_contains",\
"$pytestor.api_testor_create_user",\
"$pytestor.api_testor_current_user",\
"$pytestor.api_testor_e_functions",\
"$pytestor.api_testor_e_procedures",\
"$pytestor.api_testor_equals",\
"$pytestor.api_testor_error",\
"$pytestor.api_testor_e_tables",\
"$pytestor.api_testor_failed",\
"$pytestor.api_testor_finish",\
"$pytestor.api_testor_greater_than",\
"$pytestor.api_testor_less_than",\
"$pytestor.api_testor_login",\
"$pytestor.api_testor_logout",\
"$pytestor.api_testor_man",\
"$pytestor.api_testor_not_contains",\
"$pytestor.api_testor_not_equals",\
"$pytestor.api_testor_not_greater_than",\
"$pytestor.api_testor_not_less_than",\
"$pytestor.api_testor_not_same",\
"$pytestor.api_testor_not_true",\
"$pytestor.api_testor_option",\
"$pytestor.api_testor_pattern",\
"$pytestor.api_testor_result",\
"$pytestor.api_testor_same",\
"$pytestor.api_testor_shutdown",\
"$pytestor.api_testor_source_list",\
"$pytestor.api_testor_source",\
"$pytestor.api_testor_startup",\
"$pytestor.api_testor_success",\
"$pytestor.api_testor_suite_case",\
"$pytestor.api_testor_suite",\
"$pytestor.api_testor_test",\
"$pytestor.api_testor_true",\
"$pytestor.api_testor_user_rights",\
"$pytestor.api_testor_version"\
]

    nonl = "|g_pywifide_any|g_pywifide_all|g_pywifide_abs|g_pywifide_range|g_pywifide_bin|g_pywifide_bool|g_pywifide_bytearray|g_pywifide_str|"

    for i in range( len( supported_src ) ):
        if func_name == supported_src[i]:
            func_name = supported_tag[i]
            break
        if ('pytestor.' + func_name) == supported_src[i]:
            func_name = supported_tag[i]
            break

    php_scrp += func_name + "( "
    text = ""
    for node_bd in node.args:
        if text != "":
            text += ", "
        val = g_vst_node( node_bd )
        val = val.replace('__tuple__s__', 'g_pywifide_tuple( ')
        val = val.replace('__tuple__e__', ' )')
        if val.find( "+" ) >= 0 and val.find( '"' ):
          val = val.replace( "+", "." )
        text += val
    if nonl.find( "|" + func_name + "|" ) >= 0:
        php_scrp += text + " ) "
    else:
        php_scrp += text + " );//\n"
    return php_scrp

def g_ast_unary_op( node, indent = "" ):
    php_scrp = ""
    php_scrp += g_vst_node( node.op ) + g_vst_node( node.operand )
    return php_scrp

def g_ast_tuple( node, indent = "" ):
    php_scrp = ""
    text = ""
    for node_bd in node.elts:
        if text != "":
            text += ", "
        text += g_vst_node( node_bd, False, indent )
    php_scrp += "__tuple__s__ " + text + " __tuple__e__"
    return php_scrp

def g_ast_for( node, indent = "" ):
    php_scrp = ""
    php_scrp += f"{indent}" + "foreach ( " + g_vst_node( node.iter, False, indent ) + " as " + g_vst_node( node.target, False, indent ) + " ) {\n"
    for node_bd in node.body:
        php_scrp += f"{indent}" + g_vst_node( node_bd, False, indent + "  " ) + "\n"
    php_scrp += f"{indent}" + "}\n"
    return php_scrp

def g_ast_unknown( node, indent = "" ):
    php_scrp = "// " + str( node ) + "\n"

    return php_scrp

def g_vst_children( mod, indent = "" ):
    php_scrp = ""
    for node in ast.iter_child_nodes( mod ):
        php_scrp += g_vst_node( node, indent )
    return php_scrp

def g_vst_node( node, visit_children = False, indent = "" ):
    php_scrp = ""
    if isinstance( node, ast.Constant ):
        php_scrp += g_ast_constant( node, indent )
    elif isinstance( node, ast.For ):
        php_scrp += g_ast_for( node, indent )
    elif isinstance( node, ast.Call ):
        php_scrp += g_ast_call( node, indent )
    elif isinstance( node, ast.Expr ):
        php_scrp += g_ast_expr( node, indent )
    elif isinstance( node, ast.Tuple ):
        php_scrp += g_ast_tuple( node, indent )
    elif isinstance( node, ast.UnaryOp ):
        php_scrp += g_ast_unary_op( node, indent )
    elif isinstance( node, ast.Subscript ):
        php_scrp += g_ast_subscript( node, indent )
    elif isinstance( node, ast.Attribute ):
        php_scrp += g_ast_attribute( node, indent )
    elif isinstance( node, ast.Assign ):
        php_scrp += g_ast_assign( node, indent )
    elif isinstance( node, ast.AugAssign ):
        php_scrp += g_ast_aug_assign( node, indent )
    elif isinstance( node, ast.If ):
        php_scrp += g_ast_if( node, True, indent )
    elif isinstance( node, ast.Compare ):
        php_scrp += g_ast_compare( node, indent )
    elif isinstance( node, ast.Is ):
        php_scrp += g_ast_compare_ops_is( node, indent )
    elif isinstance( node, ast.IsNot ):
        php_scrp += g_ast_compare_ops_is_not( node, indent )
    elif isinstance( node, ast.Lt ):
        php_scrp += g_ast_compare_ops_less( node, indent )
    elif isinstance( node, ast.LtE ):
        php_scrp += g_ast_compare_ops_less_eq( node, indent )
    elif isinstance( node, ast.Gt ):
        php_scrp += g_ast_compare_ops_greater( node, indent )
    elif isinstance( node, ast.GtE ):
        php_scrp += g_ast_compare_ops_greater_eq( node, indent )
    elif isinstance( node, ast.Eq ):
        php_scrp += g_ast_compare_ops_equals( node, indent )
    elif isinstance( node, ast.NotEq ):
        php_scrp += g_ast_compare_ops_not_equals( node, indent )
    elif isinstance( node, ast.In ):
        php_scrp += g_ast_compare_ops_in( node, indent )
    elif isinstance( node, ast.NotIn ):
        php_scrp += g_ast_compare_ops_not_in( node, indent )
    elif isinstance( node, ast.Name ):
        php_scrp += g_ast_name( node, indent )
    elif isinstance( node, ast.List ):
        php_scrp += g_ast_list( node, indent )
    elif isinstance( node, ast.While ):
        php_scrp += g_ast_while( node, indent )
    elif isinstance( node, ast.BinOp ):
        php_scrp += g_ast_bin_op( node, indent )
    elif isinstance( node, ast.Add ) or isinstance( node, ast.UAdd ):
        php_scrp += g_ast_add( node, indent )
    elif isinstance( node, ast.Sub ) or isinstance( node, ast.USub ):
        php_scrp += g_ast_sub( node, indent )
    elif isinstance( node, ast.Mult ):
        php_scrp += g_ast_mult( node, indent )
    elif isinstance( node, ast.Div ):
        php_scrp += g_ast_div( node, indent )
    elif isinstance( node, ast.FloorDiv ):
        php_scrp += g_ast_floor_div( node, indent )
    elif isinstance( node, ast.Pow ):
        php_scrp += g_ast_pow( node, indent )
    else:
        php_scrp += g_ast_unknown( node, indent )
    if visit_children:
        php_scrp += g_vst_children( node, indent )
    return php_scrp

def g_convert( filename ):
    php_scrp = ""
    py_scrp = ""
    with open( filename, "r" ) as f:
        py_scrp = f.read()
    mod = ast.parse( py_scrp )
    php_scrp += g_vst_children( mod )
    return php_scrp

if __name__ == "__main__":
    filename = sys.argv[1]
    php_scrp = g_convert( filename )
    print( php_scrp )
