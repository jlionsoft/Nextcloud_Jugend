const { defineConfig } = require('eslint/config')
const vue = require('eslint-plugin-vue')
const globals = require('globals')
const typescriptParser = require('@typescript-eslint/parser')
const typescriptPlugin = require('@typescript-eslint/eslint-plugin')

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
	{
		files: ['**/*.{ts,tsx,vue}'],
		languageOptions: {
			parser: typescriptParser,
			parserOptions: {
				ecmaVersion: 'latest',
				sourceType: 'module',
				extraFileExtensions: ['.vue'],
			},
		},
		plugins: {
			'@typescript-eslint': typescriptPlugin,
		},
	},
	...vue.configs['flat/recommended'],
	{
		rules: {
			'vue/multi-word-component-names': 'off',
			'vue/max-attributes-per-line': 'off',
			'vue/singleline-html-element-content-newline': 'off',
			'vue/html-indent': 'off',
			'vue/html-closing-bracket-newline': 'off',
		},
	},
])
