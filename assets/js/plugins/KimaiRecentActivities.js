/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiRecentActivities: responsible to reload the users recent activities
 */

import KimaiPlugin from '../KimaiPlugin';

export default class KimaiRecentActivities extends KimaiPlugin {

    constructor(selector) {
        super();
        this._selector = selector;
    }

    getId() {
        return 'recent-activities';
    }

    init() {
        const menu = document.querySelector(this._selector);
        // the menu can be hidden if user has no permissions to see it
        if (menu === null) {
            return;
        }

        const dropdown = menu.querySelector('.dropdown-menu');

        this.attributes = dropdown.dataset;
        this.itemList = dropdown.querySelector('.menu');

        const handle = () => { this._reloadRecentActivities(); };

        // don't block initial browser rendering
        setTimeout(handle, 500);

        document.addEventListener('kimai.recentActivities', handle);
        document.addEventListener('kimai.timesheetUpdate', handle);
        document.addEventListener('kimai.timesheetDelete', handle);
        document.addEventListener('kimai.activityUpdate', handle);
        document.addEventListener('kimai.activityDelete', handle);
        document.addEventListener('kimai.projectUpdate', handle);
        document.addEventListener('kimai.projectDelete', handle);
        document.addEventListener('kimai.customerUpdate', handle);
        document.addEventListener('kimai.customerDelete', handle);
    }

    _emptyList() {
        this.itemList.innerHTML = '';
    }

    _setEntries(entries) {
        if (entries.length === 0) {
            this._emptyList();
            return;
        }

        const isList = this.itemList.nodeName.toUpperCase() === 'UL';
        const itemClass = (this.attributes['itemClass'] !== undefined ? this.attributes['itemClass'] : '');

        let htmlToInsert = '';

        /** @type {KimaiEscape} ESCAPER */
        const ESCAPER = this.getPlugin('escape');

        for (let timesheet of entries) {
            const label = this.attributes['template']
                .replace('%customer%', ESCAPER.escapeForHtml(timesheet.project.customer.name))
                .replace('%project%', ESCAPER.escapeForHtml(timesheet.project.name))
                .replace('%activity%', ESCAPER.escapeForHtml(timesheet.activity.name))
            ;

            const icon = this.attributes['icon'] !== undefined ? `<i class="${ this.attributes['icon'] }"></i>` : '';

            const linkToInsert =
                `<a href="${ this.attributes['href'].replace('000', timesheet.id) }" data-event="kimai.timesheetStart kimai.timesheetUpdate" class="api-link ${itemClass}" data-method="PATCH" data-msg-error="timesheet.start.error" data-msg-success="timesheet.start.success">` +
                    `${ icon } ${ label }` +
                `</a>`;

            if (isList) {
                htmlToInsert += `<li>` + linkToInsert + `</li>`;
            } else {
                htmlToInsert += linkToInsert;
            }
        }

        this.itemList.innerHTML = htmlToInsert;
    }

    _reloadRecentActivities() {
        /** @type {KimaiAPI} API */
        const API = this.getContainer().getPlugin('api');

        API.get(this.attributes['api'], {}, (result) => {
            this._setEntries(result);
        });
    }

}
