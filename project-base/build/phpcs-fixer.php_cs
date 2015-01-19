<?php

return Symfony\CS\Config\Config::create()
	->level(Symfony\CS\FixerInterface::NONE_LEVEL)
	->fixers([
		'elseif',
		'function_call_space',
		'function_declaration',
		'ordered_use',
		'psr0',
		'short_tag',
	]);