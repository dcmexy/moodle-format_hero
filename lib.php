<?php
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

/**
 * This file contains main class for the course format Topic
 *
 * @package     format_hero
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the hero course format
 *
 * @package     format_hero
 * @copyright   2019 Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_hero extends format_base {

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() : bool {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) : string {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the hero course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_hero');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {

        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            $url->param('section', $sectionno);
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * hero format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        $icons = array (
            'report' => get_string('areachart', 'format_hero'),
            'notifications' => get_string('bell', 'format_hero'),
            'calc' => get_string('calculator', 'format_hero'),
            'calendar' => get_string('calendar', 'format_hero'),
            'duration' => get_string('clock', 'format_hero'),
            'email' => get_string('envelope', 'format_hero'),
            'siteevent' => get_string('globe', 'format_hero'),
            'info' => get_string('info', 'format_hero'),
            'new' => get_string('lightning', 'format_hero'),
            'stats' => get_string('linechart', 'format_hero'),
            'payment' => get_string('money', 'format_hero'),
            'news' => get_string('newspaper', 'format_hero'),
            'grades' => get_string('openbook', 'format_hero'),
            'groupn' => get_string('person', 'format_hero'),
            'group' => get_string('people', 'format_hero'),
            'questions' => get_string('question', 'format_hero'),
            'dashboard' => get_string('speedometer', 'format_hero'),
            'star-rating' => get_string('star', 'format_hero'),
            'checked' => get_string('tick', 'format_hero'),
        );

        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'completionstatus' => array(
                    'default' => 1,
                    'type' => PARAM_INT,
                ),
                'completiontracking' => array(
                    'default' => 1,
                    'type' => PARAM_INT,
                ),
                'detailsheading' => array(
                    'default' => get_string('detailsheading', 'format_hero'),
                    'type' => PARAM_TEXT,
                ),
                'sectionheading1' => array(
                    'default' => '',
                    'type' => PARAM_TEXT,
                ),
                'sectiontext1' => array(
                    'default' => '',
                    'type' => PARAM_RAW,
                    'element' => 'editor'
                ),
                'sectionicon1' => array(
                    'default' => '',
                    'type' => PARAM_ALPHAEXT,
                ),
                'sectionheading2' => array(
                    'default' => '',
                    'type' => PARAM_TEXT,
                ),
                'sectiontext2' => array(
                    'default' => '',
                    'type' => PARAM_RAW,
                    'element' => 'editor'
                ),
                'sectionicon2' => array(
                    'default' => '',
                    'type' => PARAM_ALPHAEXT,
                ),
                'sectionheading3' => array(
                    'default' => '',
                    'type' => PARAM_TEXT,
                ),
                'sectiontext3' => array(
                    'default' => '',
                    'type' => PARAM_RAW,
                    'element' => 'editor'
                ),
                'sectionicon3' => array(
                    'default' => '',
                    'type' => PARAM_ALPHAEXT,
                ),
                'sectionheading4' => array(
                    'default' => '',
                    'type' => PARAM_TEXT,
                ),
                'sectiontext4' => array(
                    'default' => '',
                    'type' => PARAM_RAW,
                    'element' => 'editor'
                ),
                'sectionicon4' => array(
                    'default' => '',
                    'type' => PARAM_ALPHAEXT,
                ),
                'sectionheading5' => array(
                    'default' => '',
                    'type' => PARAM_TEXT,
                ),
                'sectiontext5' => array(
                    'default' => '',
                    'type' => PARAM_RAW,
                    'element' => 'editor'
                ),
                'sectionicon5' => array(
                    'default' => '',
                    'type' => PARAM_ALPHAEXT,
                ),
                'resourcesheading' => array(
                    'default' => get_string('resourcesheading', 'format_hero'),
                    'type' => PARAM_TEXT,
                ),
                'reversedisplay' => array(
                    'default' => 0,
                    'type' => PARAM_INT,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = array(
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'completionstatus' => array(
                    'label' => get_string('completionstatus', 'format_hero'),
                    'element_type' => 'advcheckbox',
                    'help' => 'completionstatus',
                    'help_component' => 'format_hero',
                    'element_attributes' => array( get_string('completionstatus_label', 'format_hero'))
                ),
                'completiontracking' => array(
                    'label' => get_string('completiontracking', 'format_hero'),
                    'element_type' => 'advcheckbox',
                    'help' => 'completiontracking',
                    'help_component' => 'format_hero',
                    'element_attributes' => array( get_string('completiontracking_label', 'format_hero'))
                ),
                'detailsheading' => array(
                    'label' => get_string('detailsheading_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'detailsheading',
                    'help_component' => 'format_hero',
                ),
                'sectionheading1' => array(
                    'label' => get_string('sectionheading1_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'sectionheading1',
                    'help_component' => 'format_hero',
                ),
                'sectiontext1' => array(
                    'label' => get_string('sectiontext1_label', 'format_hero'),
                    'element_type' => 'editor',
                    'help' => 'sectiontext1',
                    'help_component' => 'format_hero',
                    'element_attributes' => array(
                        'trusttext' => 0,
                        'enable_filemanagement' => false
                    )
                ),
                'sectionicon1' => array(
                    'label' => get_string('sectionicon1_label', 'format_hero'),
                    'element_type' => 'select',
                    'help' => 'sectionicon1',
                    'help_component' => 'format_hero',
                    'element_attributes' => array($icons),
                ),
                'sectionheading2' => array(
                    'label' => get_string('sectionheading2_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'sectionheading2',
                    'help_component' => 'format_hero',
                ),
                'sectiontext2' => array(
                    'label' => get_string('sectiontext2_label', 'format_hero'),
                    'element_type' => 'editor',
                    'help' => 'sectiontext2',
                    'help_component' => 'format_hero',
                    'element_attributes' => array(
                        'trusttext' => 0,
                        'enable_filemanagement' => false
                    )
                ),
                'sectionicon2' => array(
                    'label' => get_string('sectionicon2_label', 'format_hero'),
                    'element_type' => 'select',
                    'help' => 'sectionicon2',
                    'help_component' => 'format_hero',
                    'element_attributes' => array($icons),
                ),
                'sectionheading3' => array(
                    'label' => get_string('sectionheading3_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'sectionheading3',
                    'help_component' => 'format_hero',
                ),
                'sectiontext3' => array(
                    'label' => get_string('sectiontext3_label', 'format_hero'),
                    'element_type' => 'editor',
                    'help' => 'sectiontext3',
                    'help_component' => 'format_hero',
                    'element_attributes' => array(
                        'trusttext' => 0,
                        'enable_filemanagement' => false
                    )
                ),
                'sectionicon3' => array(
                    'label' => get_string('sectionicon3_label', 'format_hero'),
                    'element_type' => 'select',
                    'help' => 'sectionicon3',
                    'help_component' => 'format_hero',
                    'element_attributes' => array($icons),
                ),
                'sectionheading4' => array(
                    'label' => get_string('sectionheading4_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'sectionheading4',
                    'help_component' => 'format_hero',
                ),
                'sectiontext4' => array(
                    'label' => get_string('sectiontext4_label', 'format_hero'),
                    'element_type' => 'editor',
                    'help' => 'sectiontext4',
                    'help_component' => 'format_hero',
                    'element_attributes' => array(
                        'trusttext' => 0,
                        'enable_filemanagement' => false
                    )
                ),
                'sectionicon4' => array(
                    'label' => get_string('sectionicon4_label', 'format_hero'),
                    'element_type' => 'select',
                    'help' => 'sectionicon4',
                    'help_component' => 'format_hero',
                    'element_attributes' => array($icons),
                ),
                'sectionheading5' => array(
                    'label' => get_string('sectionheading5_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'sectionheading5',
                    'help_component' => 'format_hero',
                ),
                'sectiontext5' => array(
                    'label' => get_string('sectiontext5_label', 'format_hero'),
                    'element_type' => 'editor',
                    'help' => 'sectiontext5',
                    'help_component' => 'format_hero',
                    'element_attributes' => array(
                        'trusttext' => 0,
                        'enable_filemanagement' => false
                    )
                ),
                'sectionicon5' => array(
                    'label' => get_string('sectionicon5_label', 'format_hero'),
                    'element_type' => 'select',
                    'help' => 'sectionicon5',
                    'help_component' => 'format_hero',
                    'element_attributes' => array($icons),
                ),
                'resourcesheading' => array(
                    'label' => get_string('resourcesheading_label', 'format_hero'),
                    'element_type' => 'text',
                    'help' => 'resourcesheading',
                    'help_component' => 'format_hero',
                ),
                'reversedisplay' => array(
                    'label' => get_string('reversedisplay', 'format_hero'),
                    'element_type' => 'advcheckbox',
                    'help' => 'reversedisplay',
                    'help_component' => 'format_hero',
                    'element_attributes' => array( get_string('reversedisplay_label', 'format_hero'))
                ),
            );

            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'hero', we try to copy options
     * 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Prepares values of course or section format options before storing them in DB
     *
     * If an option has invalid value it is not returned
     *
     * @param array $rawdata associative array of the proposed course/section format options
     * @param int|null $sectionid null if it is course format option
     * @return array array of options that have valid values
     */
    protected function validate_format_options(array $rawdata, int $sectionid = null) : array {
        if (!$sectionid) {
            $allformatoptions = $this->course_format_options(true);
        } else {
            $allformatoptions = $this->section_format_options(true);
        }
        $data = array_intersect_key($rawdata, $allformatoptions);

        foreach ($data as $key => $value) {
            $option = $allformatoptions[$key] + ['type' => PARAM_RAW, 'element_type' => null, 'element_attributes' => [[]]];
            if (is_array($value)) {
                $cleanedarray = clean_param_array($value, $option['type']);
                $data[$key] = json_encode($cleanedarray);
            } else {
                $data[$key] = clean_param($value, $option['type']);
            }

            if ($option['element_type'] === 'select' && !array_key_exists($data[$key], $option['element_attributes'][0])) {
                // Value invalid for select element, skip.
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * Returns the format options stored for this course or course section
     *
     * When overriding please note that this function is called from rebuild_course_cache()
     * and section_info object, therefore using of get_fast_modinfo() and/or any function that
     * accesses it may lead to recursion.
     *
     * @param null|int|stdClass|section_info $section if null the course format options will be returned
     *     otherwise options for specified section will be returned. This can be either
     *     section object or relative section number (field course_sections.section)
     * @return array
     */
    public function get_format_options($section = null) {
        global $DB;

        if ($section === null) {
            $options = $this->course_format_options();
        } else {
            $options = $this->section_format_options();
        }

        if (empty($options)) {
            // There are no option for course/sections anyway, no need to go further.
            return array();
        }
        if ($section === null) {
            // Course format options will be returned.
            $sectionid = 0;
        } else if ($this->courseid && isset($section->id)) {
            // Course section format options will be returned.
            $sectionid = $section->id;
        } else if ($this->courseid && is_int($section) &&
            ($sectionobj = $DB->get_record('course_sections',
                array('section' => $section, 'course' => $this->courseid), 'id'))) {
                    // Course section format options will be returned.
                    $sectionid = $sectionobj->id;
        } else {
            // Non-existing (yet) section was passed as an argument,
            // default format options for course section will be returned.
            $sectionid = -1;
        }
        if (!array_key_exists($sectionid, $this->formatoptions)) {
            $this->formatoptions[$sectionid] = array();
            // First fill with default values.
            foreach ($options as $optionname => $optionparams) {
                $this->formatoptions[$sectionid][$optionname] = null;
                if (array_key_exists('default', $optionparams)) {
                    $this->formatoptions[$sectionid][$optionname] = $optionparams['default'];
                }
            }
            if ($this->courseid && $sectionid !== -1) {
                // Overwrite the default options values with those stored in course_format_options table.
                // Nothing can be stored if we are interested in generic course ($this->courseid == 0)
                // or generic section ($sectionid === 0).
                $records = $DB->get_records('course_format_options',
                    array('courseid' => $this->courseid,
                          'format' => $this->format,
                          'sectionid' => $sectionid
                          ), '', 'id,name,value');
                foreach ($records as $record) {
                    if (array_key_exists($record->name, $this->formatoptions[$sectionid])) {
                        $value = $record->value;
                        if ($value !== null && isset($options[$record->name]['type'])) {
                            // This will convert string value to number if needed.
                            $value = clean_param($value, $options[$record->name]['type']);
                        }

                        if (!empty($options[$record->name]['element']) && $options[$record->name]['element'] === 'editor') {
                            $this->formatoptions[$sectionid][$record->name] = json_decode($value, true);
                        } else {
                            $this->formatoptions[$sectionid][$record->name] = $value;
                        }
                    }
                }
            }
        }
        return $this->formatoptions[$sectionid];
    }
    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) : bool {
        return true;
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_hero');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_hero', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() : bool {
        return false;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) : bool {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities.
     *
     * Course formats should register.
     *
     * @param stdClass|section_info $section
     * @param string $action
     * @param int $sr
     * @return null|array|stdClass any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'hero' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_hero');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }

    /**
     * Allows course format to execute code on moodle_page::set_course()
     *
     * This function is executed before the output starts.
     *
     * If everything is configured correctly, user is redirected from the
     * default course view page the last activity or section they viewed.
     *
     * @param moodle_page $page instance of page calling set_course.
     */
    public function page_set_course(moodle_page $page) : void {

        if ($page->has_set_url()) {
            $url = new \format_hero\course_url($page->url);
            $params = $url->get_params();
            $path = $url->get_path();

            // We only want to redirect if we are requesting the main course page.
            // If we have linked to a section don't redirect.
            if (!array_key_exists('section', $params) && preg_match('/course\/view\.php/', $path)) {
                $courseid = $page->context->get_course_context()->instanceid;
                $preferencename = 'format_hero_resume_courseid_' . $courseid;
                $userpreference = json_decode(get_user_preferences($preferencename), true);
                $courseformatoptions = course_get_format($courseid)->get_format_options();

                // Check functionality is enabled in config.
                if (empty($courseformatoptions['completiontracking'])) {
                    return;
                }

                // If user preference is empty exit early.
                if (empty($userpreference)) {
                    return;
                }

                // Check if context still exists before we try to redirect to it.
                $preferncecontextid = context::instance_by_id($userpreference['contextid']);
                if (!$preferncecontextid) {
                    return;
                }

                // Check if module still exists.
                if (!preg_match('/course\/view\.php/', $userpreference['path'])) { // Only action for mods.
                    try {
                        $cm = get_fast_modinfo($courseid)->get_cm($preferncecontextid->instanceid);
                    } catch (\moodle_exception $e) {
                        return; // Return if not found.
                    }
                    if ($cm->deletioninprogress) {
                        return; // Return if being deleted.
                    }
                }

                // Check if we are not comming from a child context of the course.
                $previouscontext = get_user_preferences('theme_cataweseome_previous_contextid');
                if (!$previouscontext) {
                    return;
                }

                $childrenids = $page->context->get_child_contexts();
                $childrenids[$page->context->id] = $page->context->id;

                if (array_key_exists($previouscontext, $childrenids)) {
                    return;
                }

                $redirecturl = new moodle_url($userpreference['path'], $userpreference['params'], $userpreference['anchor']);

                // Only redirect if preference URL is not base course URL.
                $courseurl = new moodle_url('/course/view.php', array('id' => $courseid));
                if ($redirecturl->compare($courseurl)) {
                    return;
                }

                // We've passed all the checks, redrect user.
                redirect($redirecturl);

            }

        }

    }

}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_hero_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'hero'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Store information about the course or module the user is viewing.
 */
function format_hero_before_footer() {
    global $PAGE;
    $contextlevel = $PAGE->context->contextlevel;
    $contextid = $PAGE->context->id;
    if (($contextlevel != CONTEXT_COURSE) && ($contextlevel != CONTEXT_MODULE)) {
        return;  // Exit early if we are not in a course or module context.
    }

    if ($PAGE->user_is_editing()) {
        return; // Return early if user is editing.
    }

    $courseid = $PAGE->context->get_course_context()->instanceid;
    $preferencename = 'format_hero_resume_courseid_' . $courseid;
    $url = $PAGE->context->get_url();
    $rawurl = $PAGE->url;

    if ($contextlevel == CONTEXT_COURSE) {
        $targeturl = new \format_hero\course_url($rawurl);
    } else if ($contextlevel == CONTEXT_MODULE) {
        if (strpos($rawurl->get_path(), 'mod/resource') !== false) {
            $targeturl = new \format_hero\course_url($url, ['forceview' => 1]);
        } else {
            $targeturl = new \format_hero\course_url($url);
        }
    }

    $path = $targeturl->get_path();
    if (preg_match('/view\.php/', $path)) {

        $preference = array(
            'path' => $path,
            'params' => $targeturl->get_params(),
            'anchor' => $targeturl->get_anchor(),
            'contextid' => $contextid
        );
        // We have what we need lets store it.
        set_user_preference($preferencename, json_encode($preference));
    }
}

