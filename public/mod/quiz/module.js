// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/* eslint camelcase: off */

/**
 * JavaScript library for the quiz module.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

M.mod_quiz = M.mod_quiz || {};

M.mod_quiz.init_attempt_form = function(Y) {
    require(['core_question/question_engine'], function(qEngine) {
        qEngine.initForm('#responseform');
    });
    require(['mod_quiz/timer'], function(timer) {
        Y.on('submit', timer.stop, '#responseform');
    });
    require(['core_form/changechecker'], function(FormChangeChecker) {
        FormChangeChecker.watchFormById('responseform');
    });
};

M.mod_quiz.init_review_form = function(Y) {
    require(['core_question/question_engine'], function(qEngine) {
        qEngine.initForm('.questionflagsaveform');
    });
    Y.on('submit', function(e) { e.halt(); }, '.questionflagsaveform');
};

M.mod_quiz.init_comment_popup = function(Y) {
    // Add a close button to the window.
    var closebutton = Y.Node.create('<input type="button" class="btn btn-secondary" />');
    closebutton.set('value', M.util.get_string('cancel', 'moodle'));
    Y.one('#id_submitbutton').ancestor().append(closebutton);
    Y.on('click', function() { window.close() }, closebutton);
}

M.mod_quiz.filesUpload = {
    /**
     * YUI object.
     */
    Y: null,

    /**
     * Number of files uploading.
     */
    numberFilesUploading: 0,

    /**
     * Disable navigation block when uploading and enable navigation block when all files are uploaded.
     */
    disableNavPanel: function() {
        var quizNavigationBlock = document.getElementById('mod_quiz_navblock');
        if (quizNavigationBlock) {
            if (M.mod_quiz.filesUpload.numberFilesUploading) {
                quizNavigationBlock.classList.add('nav-disabled');
            } else {
                quizNavigationBlock.classList.remove('nav-disabled');
            }
        }
    }
};

M.mod_quiz.nav = M.mod_quiz.nav || {};

M.mod_quiz.nav.update_flag_state = function(attemptid, questionid, newstate) {
    var Y = M.mod_quiz.nav.Y;
    var navlink = Y.one('#quiznavbutton' + questionid);
    navlink.removeClass('flagged');
    if (newstate == 1) {
        navlink.addClass('flagged');
        navlink.one('.accesshide .flagstate').setContent(M.util.get_string('flagged', 'question'));
    } else {
        navlink.one('.accesshide .flagstate').setContent('');
    }
};

M.mod_quiz.nav.init = function(Y) {
    M.mod_quiz.nav.Y = Y;

    Y.all('#quiznojswarning').remove();

    var form = Y.one('#responseform');
    if (form) {
        function nav_to_page(pageno) {
            Y.one('#followingpage').set('value', pageno);

            // Automatically submit the form. We do it this strange way because just
            // calling form.submit() does not run the form's submit event handlers.
            var submit = form.one('input[name="next"]');
            submit.set('name', '');
            submit.getDOMNode().click();
        };

        Y.delegate('click', function(e) {
            if (this.hasClass('thispage')) {
                return;
            }

            e.preventDefault();

            var pageidmatch = this.get('href').match(/page=(\d+)/);
            var pageno;
            if (pageidmatch) {
                pageno = pageidmatch[1];
            } else {
                pageno = 0;
            }

            var questionidmatch = this.get('href').match(/#question-(\d+)-(\d+)/);
            if (questionidmatch) {
                form.set('action', form.get('action') + questionidmatch[0]);
            }

            nav_to_page(pageno);
        }, document.body, '.qnbutton');
    }

    if (Y.one('a.endtestlink')) {
        Y.on('click', function(e) {
            e.preventDefault();
            nav_to_page(-1);
        }, 'a.endtestlink');
    }

    // Navigation buttons should be disabled when the files are uploading.
    require(['core_form/events'], function(formEvent) {
        document.addEventListener(formEvent.eventTypes.uploadStarted, function() {
            M.mod_quiz.filesUpload.numberFilesUploading++;
            M.mod_quiz.filesUpload.disableNavPanel();
        });

        document.addEventListener(formEvent.eventTypes.uploadCompleted, function() {
            M.mod_quiz.filesUpload.numberFilesUploading--;
            M.mod_quiz.filesUpload.disableNavPanel();
        });
    });

    if (M.core_question_flags) {
        M.core_question_flags.add_listener(M.mod_quiz.nav.update_flag_state);
    }
};

M.mod_quiz.secure_window = {
    init: function(Y) {
        if (window.location.href.substring(0, 4) == 'file') {
            window.location = 'about:blank';
        }
        Y.delegate('contextmenu', M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('mousedown',   M.mod_quiz.secure_window.prevent_mouse, 'body', '*');
        Y.delegate('mouseup',     M.mod_quiz.secure_window.prevent_mouse, 'body', '*');
        Y.delegate('dragstart',   M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('selectstart', M.mod_quiz.secure_window.prevent_selection, document, '*');
        Y.delegate('cut',         M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('copy',        M.mod_quiz.secure_window.prevent, document, '*');
        Y.delegate('paste',       M.mod_quiz.secure_window.prevent, document, '*');
        Y.on('beforeprint', function() {
            Y.one(document.body).setStyle('display', 'none');
        }, window);
        Y.on('afterprint', function() {
            Y.one(document.body).setStyle('display', 'block');
        }, window);
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'press:67,86,88+ctrl');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'up:67,86,88+ctrl');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'down:67,86,88+ctrl');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'press:67,86,88+meta');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'up:67,86,88+meta');
        Y.on('key', M.mod_quiz.secure_window.prevent, '*', 'down:67,86,88+meta');
    },

    is_content_editable: function(n) {
        if (n.test('[contenteditable=true]')) {
            return true;
        }
        n = n.get('parentNode');
        if (n === null) {
            return false;
        }
        return M.mod_quiz.secure_window.is_content_editable(n);
    },

    prevent_selection: function(e) {
        return false;
    },

    prevent: function(e) {
        alert(M.util.get_string('functiondisabledbysecuremode', 'quiz'));
        e.halt();
    },

    prevent_mouse: function(e) {
        if (e.button == 1 && /^(INPUT|TEXTAREA|BUTTON|SELECT|LABEL|A)$/i.test(e.target.get('tagName'))) {
            // Left click on a button or similar. No worries.
            return;
        }
        if (e.button == 1 && M.mod_quiz.secure_window.is_content_editable(e.target)) {
            // Left click in Atto or similar.
            return;
        }
        e.halt();
    },

    /**
     * Initialize the event listener for the secure window close button
     *
     * @param {Object} Y YUI instance. When called from renderer, this parameter precedes the others
     * @param {String} url
     */
    init_close_button: function(Y, url) {
        Y.on('click', function(e) {
            M.mod_quiz.secure_window.close(Y, url, 0);
        }, '#secureclosebutton');
    },

    /**
     * Close the secure window, or redirect to URL if the opener is no longer present
     *
     * @param {Object} Y YUI instance. When called from renderer, this parameter precedes the others
     * @param {String} url
     * @param {Number} delay
     */
    close: function(Y, url, delay) {
        setTimeout(function() {
            if (window.opener) {
                window.opener.document.location.reload();
                window.close();
            } else {
                window.location.href = url;
            }
        }, delay*1000);
    }
};
