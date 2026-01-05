/**
 * SPDX-FileCopyrightText: 2024 Your Name <admin@example.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import App from './views/App.vue'

Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#federatedtalklink-app')
