module.exports = {
     // Disable some dumb Moodle core rules from https://github.com/moodle/moodle/blob/main/.eslintrc .
    rules: {
        'no-console': 'off',
        'max-len': 'off',
        // I would like to have this rule on, but have one variable that I want mixed case.
        'camelcase': 'off'
    }
};