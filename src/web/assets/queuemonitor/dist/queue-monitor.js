(function() {
    'use strict';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQueueMonitor);
    } else {
        initQueueMonitor();
    }

    function initQueueMonitor() {
        var container = document.getElementById('queue-monitor');
        if (!container) {
            return;
        }

        var config = window.QueueMonitorConfig || {};
        var baseUrl = window.location.pathname;

        // Helper to build URLs
        function buildUrl(queueId, jobId) {
            var params = new URLSearchParams();
            if (queueId && queueId !== 'overview') {
                params.set('queueId', queueId);
            }
            if (jobId) {
                params.set('jobId', jobId);
            }
            var queryString = params.toString();
            return queryString ? baseUrl + '?' + queryString : baseUrl;
        }

        var REFRESH_INTERVAL = config.refreshInterval || 5000;

        new Vue({
            el: '#main',
            delimiters: ['[[', ']]'],
            data: function() {
                var params = new URLSearchParams(window.location.search);
                var queueId = params.get('queueId');
                var jobId = params.get('jobId');
                var queueIds = Object.keys(config.queues || {});
                var hasSingleQueue = queueIds.length === 1;

                // If only one queue exists and no queueId specified, auto-select it
                var defaultQueueId = 'overview';
                if (hasSingleQueue && !queueId) {
                    defaultQueueId = queueIds[0];
                    queueId = queueIds[0];
                }

                return {
                    selectedQueueId: (queueId && config.queues[queueId]) ? queueId : defaultQueueId,
                    activeQueueId: (queueId && config.queues[queueId]) ? queueId : (hasSingleQueue ? queueIds[0] : null),
                    initialJobId: jobId,
                    queues: config.queues || {},
                    jobs: [],
                    jobsByQueue: {},
                    statsByQueue: {},
                    totalJobs: 0,
                    activeJob: null,
                    loading: false,
                    actionLoading: false,
                    baseUrl: baseUrl,
                    refreshTimer: null,
                    hasSingleQueue: hasSingleQueue,
                };
            },
            computed: {
                hasAnyJobs: function() {
                    for (var queueId in this.statsByQueue) {
                        if (this.statsByQueue[queueId].total > 0) {
                            return true;
                        }
                    }
                    return false;
                },
            },
            mounted: function() {
                document.getElementById('queue-monitor').classList.remove('hidden');

                if (this.selectedQueueId === 'overview') {
                    this.loadAllQueues();
                } else {
                    this.loadJobs(this.selectedQueueId);
                    if (this.initialJobId) {
                        this.loadActiveJob(this.initialJobId);
                    }
                }

                // Start auto-refresh
                this.startAutoRefresh();

                // Initial footer update
                this.updateFooter();
            },
            beforeDestroy: function() {
                this.stopAutoRefresh();
            },
            methods: {
                getQueueUrl: function(queueId) {
                    return buildUrl(queueId, null);
                },

                getJobUrl: function(queueId, jobId) {
                    return buildUrl(queueId, jobId);
                },

                getOverviewUrl: function() {
                    return baseUrl;
                },

                onQueueChange: function() {
                    // Navigate using normal link
                    window.location.href = buildUrl(this.selectedQueueId, null);
                },

                loadAllQueues: function() {
                    var self = this;
                    self.loading = true;

                    var promises = Object.keys(self.queues).map(function(queueId) {
                        return self.fetchQueueJobs(queueId);
                    });

                    Promise.all(promises)
                        .catch(function(error) {
                            console.error('Failed to load queues:', error);
                            Craft.cp.displayError(Craft.t('queue-manager', 'An error occurred.'));
                        })
                        .finally(function() {
                            self.loading = false;
                        });
                },

                fetchQueueJobs: function(queueId) {
                    var self = this;
                    var url = config.endpoints.getJobInfo + '&queueId=' + encodeURIComponent(queueId);

                    return fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        self.$set(self.jobsByQueue, queueId, data.jobs || []);
                        self.$set(self.statsByQueue, queueId, data.stats || {});
                    });
                },

                loadJobs: function(queueId) {
                    var self = this;
                    self.loading = true;

                    var url = config.endpoints.getJobInfo + '&queueId=' + encodeURIComponent(queueId);

                    fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        self.jobs = data.jobs || [];
                        self.totalJobs = data.stats ? data.stats.total : self.jobs.length;
                        self.$set(self.statsByQueue, queueId, data.stats || {});
                        self.updateFooter();
                    })
                    .catch(function(error) {
                        console.error('Failed to load jobs:', error);
                        Craft.cp.displayError(Craft.t('queue-manager', 'An error occurred.'));
                    })
                    .finally(function() {
                        self.loading = false;
                    });
                },

                getQueueJobs: function(queueId) {
                    return this.jobsByQueue[queueId] || [];
                },

                getQueueStats: function(queueId) {
                    return this.statsByQueue[queueId] || {};
                },

                loadActiveJob: function(jobId) {
                    var self = this;
                    var queueId = self.activeQueueId || self.selectedQueueId;
                    self.loading = true;

                    var url = config.endpoints.getJobDetails +
                        '&queueId=' + encodeURIComponent(queueId) +
                        '&jobId=' + encodeURIComponent(jobId);

                    fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        self.activeJob = data.job;
                        self.updateFooter();
                    })
                    .catch(function(error) {
                        console.error('Failed to load job details:', error);
                        Craft.cp.displayError(Craft.t('queue-manager', 'An error occurred.'));
                    })
                    .finally(function() {
                        self.loading = false;
                    });
                },

                getBackUrl: function() {
                    if (this.selectedQueueId === 'overview') {
                        return baseUrl;
                    }
                    return buildUrl(this.selectedQueueId, null);
                },

                retryJob: function(job) {
                    var queueId = this.activeQueueId || this.selectedQueueId;
                    this.performAction(config.endpoints.retry, {
                        queueId: queueId,
                        jobId: job.id,
                    }, Craft.t('queue-manager', 'Job retried.'));
                },

                retryJobInQueue: function(queueId, job) {
                    var self = this;
                    this.performAction(config.endpoints.retry, {
                        queueId: queueId,
                        jobId: job.id,
                    }, Craft.t('queue-manager', 'Job retried.'), function() {
                        self.fetchQueueJobs(queueId);
                    });
                },

                retryActiveJob: function() {
                    if (this.activeJob) {
                        this.retryJob(this.activeJob);
                    }
                },

                releaseJob: function(job) {
                    var self = this;
                    var message = Craft.t('queue-manager', 'Are you sure you want to release this job?');
                    if (!confirm(message)) {
                        return;
                    }
                    var queueId = self.activeQueueId || self.selectedQueueId;
                    this.performAction(config.endpoints.release, {
                        queueId: queueId,
                        jobId: job.id,
                    }, Craft.t('queue-manager', 'Job released.'));
                },

                releaseJobInQueue: function(queueId, job) {
                    var self = this;
                    var message = Craft.t('queue-manager', 'Are you sure you want to release this job?');
                    if (!confirm(message)) {
                        return;
                    }
                    this.performAction(config.endpoints.release, {
                        queueId: queueId,
                        jobId: job.id,
                    }, Craft.t('queue-manager', 'Job released.'), function() {
                        self.fetchQueueJobs(queueId);
                    });
                },

                releaseActiveJob: function() {
                    var self = this;
                    if (this.activeJob) {
                        var message = Craft.t('queue-manager', 'Are you sure you want to release this job?');
                        if (!confirm(message)) {
                            return;
                        }
                        var queueId = self.activeQueueId || self.selectedQueueId;
                        this.performAction(config.endpoints.release, {
                            queueId: queueId,
                            jobId: this.activeJob.id,
                        }, Craft.t('queue-manager', 'Job released.'), function() {
                            window.location.href = self.getBackUrl();
                        });
                    }
                },

                retryAll: function() {
                    var queueId = this.activeQueueId || this.selectedQueueId;
                    this.performAction(config.endpoints.retryAll, {
                        queueId: queueId,
                    }, Craft.t('queue-manager', 'All failed jobs retried.'));
                },

                releaseAll: function() {
                    var message = Craft.t('queue-manager', 'Are you sure you want to release all jobs in this queue?');
                    if (!confirm(message)) {
                        return;
                    }
                    var queueId = this.activeQueueId || this.selectedQueueId;
                    this.performAction(config.endpoints.releaseAll, {
                        queueId: queueId,
                    }, Craft.t('queue-manager', 'All jobs released.'));
                },

                retryAllQueues: function() {
                    var self = this;
                    self.actionLoading = true;

                    var promises = Object.keys(self.queues).map(function(queueId) {
                        var formData = new FormData();
                        formData.append(config.csrfTokenName, config.csrfTokenValue);
                        formData.append('queueId', queueId);
                        return fetch(config.endpoints.retryAll, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: formData,
                            credentials: 'same-origin',
                        }).then(function(response) { return response.json(); });
                    });

                    Promise.all(promises)
                        .then(function() {
                            Craft.cp.displayNotice(Craft.t('queue-manager', 'All failed jobs retried.'));
                            self.loadAllQueues();
                        })
                        .catch(function(error) {
                            console.error('Retry all queues failed:', error);
                            Craft.cp.displayError(Craft.t('queue-manager', 'An error occurred.'));
                        })
                        .finally(function() {
                            self.actionLoading = false;
                        });
                },

                releaseAllQueues: function() {
                    var self = this;
                    var message = Craft.t('queue-manager', 'Are you sure you want to release all jobs in all queues?');
                    if (!confirm(message)) {
                        return;
                    }
                    self.actionLoading = true;

                    var promises = Object.keys(self.queues).map(function(queueId) {
                        var formData = new FormData();
                        formData.append(config.csrfTokenName, config.csrfTokenValue);
                        formData.append('queueId', queueId);
                        return fetch(config.endpoints.releaseAll, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: formData,
                            credentials: 'same-origin',
                        }).then(function(response) { return response.json(); });
                    });

                    Promise.all(promises)
                        .then(function() {
                            Craft.cp.displayNotice(Craft.t('queue-manager', 'All jobs released.'));
                            self.loadAllQueues();
                        })
                        .catch(function(error) {
                            console.error('Release all queues failed:', error);
                            Craft.cp.displayError(Craft.t('queue-manager', 'An error occurred.'));
                        })
                        .finally(function() {
                            self.actionLoading = false;
                        });
                },

                performAction: function(url, data, successMessage, callback) {
                    var self = this;
                    self.actionLoading = true;

                    var formData = new FormData();
                    formData.append(config.csrfTokenName, config.csrfTokenValue);
                    for (var key in data) {
                        formData.append(key, data[key]);
                    }

                    fetch(url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json' },
                        body: formData,
                        credentials: 'same-origin',
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(result) {
                        if (result.success !== false) {
                            Craft.cp.displayNotice(successMessage);
                            if (callback) {
                                callback();
                            } else if (self.selectedQueueId === 'overview') {
                                self.loadAllQueues();
                            } else {
                                self.loadJobs(self.selectedQueueId);
                            }
                        } else {
                            Craft.cp.displayError(result.message || Craft.t('queue-manager', 'An error occurred.'));
                        }
                    })
                    .catch(function(error) {
                        console.error('Action failed:', error);
                        Craft.cp.displayError(Craft.t('queue-manager', 'An error occurred.'));
                    })
                    .finally(function() {
                        self.actionLoading = false;
                    });
                },

                isRetryable: function(job) {
                    return job.status === 'failed';
                },

                jobStatusClass: function(status) {
                    return status === 'failed' ? 'error' : '';
                },

                jobStatusIconClass: function(status) {
                    var cls = 'status';
                    switch (status) {
                        case 'waiting': cls += ' orange'; break;
                        case 'reserved': cls += ' green'; break;
                        case 'failed': cls += ' red'; break;
                        case 'completed': cls += ' green'; break;
                    }
                    return cls;
                },

                jobStatusLabel: function(status) {
                    switch (status) {
                        case 'waiting': return Craft.t('queue-manager', 'Pending');
                        case 'reserved': return Craft.t('queue-manager', 'Reserved');
                        case 'failed': return Craft.t('queue-manager', 'Failed');
                        case 'completed': return Craft.t('queue-manager', 'Done');
                        default: return status;
                    }
                },

                startAutoRefresh: function() {
                    var self = this;
                    this.stopAutoRefresh();
                    if (REFRESH_INTERVAL > 0) {
                        this.refreshTimer = setInterval(function() {
                            self.refresh();
                        }, REFRESH_INTERVAL);
                    }
                },

                stopAutoRefresh: function() {
                    if (this.refreshTimer) {
                        clearInterval(this.refreshTimer);
                        this.refreshTimer = null;
                    }
                },

                refresh: function() {
                    // Don't refresh while an action is in progress
                    if (this.actionLoading) {
                        return;
                    }

                    if (this.activeJob) {
                        // Refresh active job details
                        this.refreshActiveJob();
                    } else if (this.selectedQueueId === 'overview') {
                        this.refreshAllQueues();
                    } else {
                        this.refreshJobs(this.selectedQueueId);
                    }
                },

                refreshAllQueues: function() {
                    var self = this;
                    // Silent refresh without showing loading spinner
                    var promises = Object.keys(self.queues).map(function(queueId) {
                        return self.fetchQueueJobs(queueId);
                    });

                    Promise.all(promises).catch(function(error) {
                        console.error('Failed to refresh queues:', error);
                    });
                },

                refreshJobs: function(queueId) {
                    var self = this;
                    var url = config.endpoints.getJobInfo + '&queueId=' + encodeURIComponent(queueId);

                    fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        self.jobs = data.jobs || [];
                        self.totalJobs = data.stats ? data.stats.total : self.jobs.length;
                        self.$set(self.statsByQueue, queueId, data.stats || {});
                        self.updateFooter();
                    })
                    .catch(function(error) {
                        console.error('Failed to refresh jobs:', error);
                    });
                },

                refreshActiveJob: function() {
                    var self = this;
                    var queueId = self.activeQueueId || self.selectedQueueId;
                    var jobId = self.activeJob.id;

                    var url = config.endpoints.getJobDetails +
                        '&queueId=' + encodeURIComponent(queueId) +
                        '&jobId=' + encodeURIComponent(jobId);

                    fetch(url, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.job) {
                            self.activeJob = data.job;
                        } else if (self.activeJob.status !== 'completed') {
                            // Job completed or was removed - update status and stop refreshing
                            self.activeJob.status = 'completed';
                            self.activeJob.progress = 100;
                            self.stopAutoRefresh();
                            Craft.cp.displayNotice(Craft.t('queue-manager', 'Job completed.'));
                        }
                    })
                    .catch(function(error) {
                        console.error('Failed to refresh job details:', error);
                    });
                },

                updateFooter: function() {
                    var footerEl = document.getElementById('footer');
                    if (!footerEl) {
                        return;
                    }

                    // Find the paragraph element we control
                    var footerP = footerEl.querySelector('p[data-queue-monitor-footer]');
                    if (!footerP) {
                        footerP = document.createElement('p');
                        footerP.setAttribute('data-queue-monitor-footer', 'true');
                        footerEl.insertBefore(footerP, footerEl.firstChild);
                    }

                    // Hide on overview and job detail, show on queue list
                    if (this.selectedQueueId === 'overview' || this.activeJob) {
                        footerEl.classList.add('hidden');
                        footerP.style.display = 'none';
                    } else {
                        footerEl.classList.remove('hidden');
                        footerP.style.display = '';
                        var jobText = this.totalJobs === 1
                            ? Craft.t('queue-manager', '1 job')
                            : Craft.t('queue-manager', '{total} jobs', { total: this.totalJobs });
                        footerP.textContent = jobText;
                    }
                },
            },
        });
    }
})();
