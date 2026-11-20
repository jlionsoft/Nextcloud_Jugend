const { defineConfig } = require('eslint/config')
const vue = require('eslint-plugin-vue')
const globals = require('globals')

module.exports = defineConfig([
	{
		files: ['**/*.{js,mjs,cjs,vue}'],
		languageOptions: {
			ecmaVersion: 'latest',
			sourceType: 'module',
			globals: {
				...globals.browser,
				...globals.node,
			},
		},
	},
	...vue.configs['flat/recommended'],
	{
		rules: {
			'vue/multi-word-component-names': 'off',
			'vue/max-attributes-per-line': 'off',
			'vue/singleline-html-element-content-newline': 'off',
			'vue/html-indent': ['error', 'tab'],
			'vue/html-closing-bracket-newline': 'off',
		},
	},
])
