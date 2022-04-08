/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiMultiUpdateForm: handle the multi update checkbox list and form
 */

import KimaiPlugin from '../KimaiPlugin';
import jQuery from "jquery";

export default class KimaiMultiUpdateTable extends KimaiPlugin {

    init() {
        const self = this;
        
        jQuery('body').
            on('change', '#multi_update_all', function(event) {
                jQuery('.multi_update_single').prop('checked', jQuery(event.target).prop('checked'));
                self.toggleForm();
            })
            .on('change', '.multi_update_single', function(event) {
                self.toggleForm();
            })
            .on('click', '.multi_update_table_action', function(event) {
                const selectedItem = jQuery(this);
                const selectedVal = selectedItem.data('href');

                const form = jQuery('#multi_update_form form');
                const selectedText = selectedItem.text();
                const ids = self.getSelectedIds();
                const question = form.attr('data-question').replace(/%action%/, selectedText).replace(/%count%/, ids.length);
                
                self.getContainer().getPlugin('alert').question(question, function(value) {
                    if (value) {
                        form.attr('action', selectedVal).submit();
                    } else {
                        jQuery('#multi_update_table_action').val('').trigger('change');
                    }
                });
            });
    }
    
    getSelectedIds()
    {
        let ids = [];
        jQuery('.multi_update_single:checked').each(function(i){
            ids[i] = jQuery(this).val();
        });

        return ids;
    }
    
    toggleForm() 
    {
        const ids = this.getSelectedIds();
        document.getElementById('multi_update_table_entities').value = ids.join(',');

        if (ids.length > 0) {
            for (const element of document.getElementsByClassName('multi_update_form_hide')) {
                element.style.setProperty('display', 'none', 'important');
            }
            document.getElementById('multi_update_form').style.display = null;//'block';
        } else {
            document.getElementById('multi_update_form').style.setProperty('display', 'none', 'important');
            for (const element of document.getElementsByClassName('multi_update_form_hide')) {
                element.style.display = null;
            }
        }
    }
    
}
