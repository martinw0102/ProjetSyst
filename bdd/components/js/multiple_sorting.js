define(function(require, exports) {
    var Class = require('class');
    var localizer = require('pgui.localizer').localizer;

    exports.MultipleSorting = Class.extend({
        init: function (sorter, $sortButton, $sortDialog) {
            var self = this;

            this.sorter = sorter;
            this.$sortDialog = $sortDialog;
            this.$table = $sortDialog.find('.multiple-sorting-table');
            this.$addLevelButton = $sortDialog.find('.add-sorting-level');
            this.$deleteLevelButton = $sortDialog.find('.delete-sorting-level');
            this.$initialLevels = this.$table.find('tr.sorting-level').clone();

            this.$sortDialog.modal({
                modal: true,
                show: false
            });

            this.$sortDialog.on('click', '.sort-button', function (e) {
                e.preventDefault();
                
                var columnsToSort = [];
                var $levels = self.$table.find('tr.sorting-level');

                self.sorter.clear();
                $levels.each(function () {
                    var columnName = $(this).find('.multi-sort-name').val();
                    self.sorter.deleteColumn(columnName);
                    self.sorter.addColumn(
                        columnName,
                        $(this).find('.multi-sort-order').val()
                    );
                });

                self.sorter.applySort();
            });

            this.$sortDialog.on('hidden', function () {
                self.$table.find('tr.sorting-level').remove();
                self.$table.find('tbody').append(self.$initialLevels);
                self._updateButtons();
            });

            $sortButton.click(function() {
                self.$sortDialog.modal('show');
            });

            this.$addLevelButton.on('click', function() {
                self._addLevel();
            });
            
            this.$deleteLevelButton.on('click', function() {
                self._deleteLevel();
            });


            this._updateButtons();
        },

        _addLevel: function () {
            var sortingLevelName = localizer.getString('SortBy');
            var sortingLevelsCount = this.$table.find('tr.sorting-level').length;

            if (sortingLevelsCount > 0) {
                sortingLevelName = localizer.getString('ThenBy');
            }

            var $columns = $('<select>').addClass('multi-sort-name');
            $.each(this.sorter.getSortableColumns(), function (i, column) {
                $columns.append(
                    $('<option>')
                        .val(column.fieldName)
                        .text(column.fieldCaption)
                );
            });

            var $sortOrderSelect = $('<select>')
                .addClass('multi-sort-order')
                .append($('<option>').val('a').text(localizer.getString('Ascending')))
                .append($('<option>').val('d').text(localizer.getString('Descending')));

            var $level = $('<tr>')
                .addClass('sorting-level')
                .append($('<td>').text(sortingLevelName))
                .append($('<td>').append($columns))
                .append($('<td>').append($sortOrderSelect));

            $level.appendTo(this.$table.find('tbody'));

            this._updateButtons();
        },

        _deleteLevel: function () {
            var $levels = this.$table.find('tr.sorting-level');
            if ($levels.length > 0) {
                $levels.last().remove();
            }

            this._updateButtons();
        },

        _updateButtons: function () {
            var sortingLevelsCount = this.$table.find('tr.sorting-level').length;
            var sortableColumnsCount = this.sorter.getSortableColumns().length;

            this.$addLevelButton.prop('disabled', sortableColumnsCount == sortingLevelsCount);
            this.$deleteLevelButton.prop('disabled', sortingLevelsCount == 0);
        }
    });
});
