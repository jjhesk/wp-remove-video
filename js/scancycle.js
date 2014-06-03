/**
 * Created by Hesk on 14年5月12日.
 */
var vid_scan_remove_progress;
jQuery(function ($) {

    function VideoBulkScanner() {
        this.currentItem = 0;
        this.posts = null;
        this.paused = false;
        this.invalid_video_clip_detected = 0;
        this.valid_vide_clip = 0;
        this.delay = 50;
        this.delayTimer = false;
        this.logList = $('#vt-bulk-scan-results .log');
        this.progressBar = $('#vt-bulk-scan-results .progress-bar');
        this.language = video_thumbnails_bulk_language;
        console.log(video_thumbnails_bulk_language);
    }

    VideoBulkScanner.prototype.log = function (text) {
        $('<li>' + text + '</li>').prependTo(this.logList).hide().slideDown(200);
        console.log(text);
    };

    VideoBulkScanner.prototype.disableSubmit = function () {
        $('#video-scan-remove-options input[type="submit"]').attr('disabled', 'disabled');
    };

    VideoBulkScanner.prototype.enableSubmit = function () {
        $('#video-scan-remove-options input[type="submit"]').removeAttr('disabled');
    };

    VideoBulkScanner.prototype.findPosts = function () {
        var data = {
            action: 'video_thumbnails_bulk_posts_query',
            params: $('#video-scan-remove-options').serialize()
        };
        var self = this;
        this.disableSubmit();
        $('#queue-count').text(this.language.working);
        $.post(ajaxurl, data, function (response) {
            //  self.posts = $.parseJSON(response);
            console.log(response);
            self.posts = response.posts;
            if (self.posts.length == 1) {
                queueText = self.language.queue_singular;
            } else {
                queueText = self.language.queue_plural.replace('%d', self.posts.length);
            }
            $('#queue-count').text(queueText);
            if (self.posts.length > 0) {
                self.enableSubmit();
            }
        });
    };

    VideoBulkScanner.prototype.startScan = function () {
        this.disableSubmit();
        this.paused = false;
        if (this.currentItem == 0) {
            this.log(this.language.started);
            this.progressBar.show();
            this.resetProgressBar();
            $('#video-scan-remove-options').slideUp();
        } else {
            this.log(this.language.resumed);
        }
        this.scanCurrentItem();
    };

    VideoBulkScanner.prototype.pauseScan = function () {
        this.clearSchedule();
        this.paused = true;
        this.log(this.language.paused);
    };

    VideoBulkScanner.prototype.toggleScan = function () {
        if (this.paused) {
            this.startScan();
        } else {
            this.pauseScan();
        }
    };

    VideoBulkScanner.prototype.scanCompleted = function () {
        if (this.posts.length == 1) {
            message = this.language.done + ' ' + this.language.final_count_singular;
        } else {
            message = this.language.done + ' ' + this.language.final_count_plural.replace('%d', this.posts.length);
        }
        this.log(message);
    };

    VideoBulkScanner.prototype.resetProgressBar = function () {
        $('#vt-bulk-scan-results .percentage').html('0%');
        this.progressBar
            .addClass('disable-animation')
            .css('width', '0')
        this.progressBar.height();
        this.progressBar.removeClass('disable-animation');
    };

    VideoBulkScanner.prototype.updateProgressBar = function () {
        console.log(percentage = ( this.currentItem + 1 ) / this.posts.length);
        if (percentage == 1) {
            progressText = this.language.done;
            this.scanCompleted();
        } else {
            progressText = Math.round(percentage * 100) + '%';
        }
        $('#vt-bulk-scan-results .percentage').html(progressText);
        this.progressBar.css('width', (percentage * 100) + '%');
    };

    VideoBulkScanner.prototype.updateCounter = function () {
        $('#vt-bulk-scan-results .stats .scanned').html((this.currentItem + 1) + '/' + this.posts.length);
        $('#vt-bulk-scan-results .stats .found-new').html(this.invalid_video_clip_detected);
        $('#vt-bulk-scan-results .stats .found-existing').html(this.valid_vide_clip);
    }

    VideoBulkScanner.prototype.updateStats = function () {
        this.updateProgressBar();
        this.updateCounter();
    }

    VideoBulkScanner.prototype.scheduleNextItem = function () {
        if (( this.currentItem + 1 ) < this.posts.length) {
            var self = this;
            self.currentItem++;
            this.delayTimer = setTimeout(function () {
                self.scanCurrentItem();
            }, this.delay);
        }
    }

    VideoBulkScanner.prototype.clearSchedule = function () {
        clearTimeout(this.delayTimer);
    }

    VideoBulkScanner.prototype.scanCurrentItem = function () {
        if (this.paused) return false;
        if (this.currentItem < this.posts.length) {
            this.log('[ID: ' + this.posts[this.currentItem] + '] ' + this.language.scanning_of.replace('%1$s', this.currentItem + 1).replace('%2$s', this.posts.length));
            var data = {
                action: 'get_scan_post_with_id',
                post_id: this.posts[this.currentItem]
            };
            var self = this;
            console.log(data);
            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: data,
                success: function (response) {
                    //  var result = $.parseJSON(response);
                    console.log(response);
                    var result = response;
                    if (result.code == 200) {
                        self.log('[ID: ' + self.posts[self.currentItem] + '] ' + self.language.found_valid);
                        self.valid_vide_clip++;
                    } else {
                        self.log('[ID: ' + self.posts[self.currentItem] + '] ' + self.language.found_invalid);
                        self.invalid_video_clip_detected++;
                    }
                    self.updateStats();
                    self.scheduleNextItem();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    self.log('[ID: ' + self.posts[self.currentItem] + '] ' + self.language.error + ' ' + errorThrown);
                    self.updateStats();
                    self.scheduleNextItem();
                }
            });
        } else {
            this.updateStats();
            this.currentItem = 0;
        }
    };
    vid_scan_remove_progress = new VideoBulkScanner();
    vid_scan_remove_progress.findPosts();
    $('#video-scan-remove-options').on('change', function (e) {
        vid_scan_remove_progress.findPosts();
    });
    /**
     * this is the place where it starts the scanning part.
     */
    $('#video-scan-remove-options').on('submit', function (e) {
        e.preventDefault();
        vid_scan_remove_progress.startScan();
    });

});