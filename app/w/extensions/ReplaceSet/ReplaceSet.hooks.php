<?php

class ReplaceSetHooks {
	static function efReplaceSetRegisterParser( &$parser ) {
		$parser->setFunctionHook( 'replaceset', array( 'ReplaceSet', 'parserFunctionObj' ), Parser::SFH_OBJECT_ARGS );
		return true;
	}
}