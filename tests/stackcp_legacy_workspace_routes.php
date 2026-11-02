<?php
/** Test-only compatibility for the two intentional workspace pagination destinations. */
declare(strict_types=1);
function sap_reviewed_workspace_routes(string $name,string $code):string {
    if($name!=='provider_stackcp_access_admin_render')return $code;
    if(!hash_equals('6df7b88c67240f983a7284dc351a561fe2ee953179530d4ac28f9a4a1566113b',hash('sha256',$code)))throw new RuntimeException('Unexpected historical renderer source.');
    $old="admin_url('provider-settings',['stackcp_access_page'=>";$new="admin_url('provider-settings',['section'=>'advanced','stackcp_access_page'=>";
    if(substr_count($code,$old)!==2)throw new RuntimeException('Expected exactly two original pagination routes.');
    $updated=str_replace($old,$new,$code);
    if(!hash_equals('679e97edf131c52b2d7d7f452eb51d7929d5a3de019eda13854a1223830069f1',hash('sha256',$updated)))throw new RuntimeException('Unexpected pagination compatibility output.');
    return $updated;
}
