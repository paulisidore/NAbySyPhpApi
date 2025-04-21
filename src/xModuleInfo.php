<?php
    /*
 * (c) Paul Isidore A. NIAMIE <paul.isidore@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace NAbySy ;
 
class ModuleMCP
{
	public string $Nom ;
	public string $Version ;
	public bool $Actif = false ;
	public string $Description="" ;
	public string $MCP_CLIENT ;
	public string $MCP_ADRESSECLT ;
	public string $MCP_TELCLT ;
}
Class TableMCP
{
	public $Nom;
	public $DBName;
	public $ID;
}

?>