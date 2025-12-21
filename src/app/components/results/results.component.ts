import { Component, inject, OnInit, signal, computed, HostListener, ElementRef } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';
import { CourseService } from '../../services/course.service';
import { TranslateModule } from '@ngx-translate/core';
import { AuthService } from '../../services/auth.service';

@Component({
    selector: 'app-results',
    standalone: true,
    imports: [CommonModule, FormsModule, DatePipe, TranslateModule],
    templateUrl: './results.component.html',
    styleUrl: './results.component.css'
})
export class ResultsComponent implements OnInit {
    private apiService = inject(ApiService);
    private courseService = inject(CourseService);
    private authService = inject(AuthService);
    private elementRef = inject(ElementRef);

    results = signal<any[]>([]);
    groups = signal<any[]>([]);
    courses = signal<any[]>([]);

    // Filter state
    search = signal('');
    selectedGroupId = signal<string>('');
    selectedCourseId = signal<string>('');
    statusFilter = signal<string>('all');

    // Dropdown UI state
    courseDropdownOpen = signal(false);
    groupDropdownOpen = signal(false);

    courseSearchQuery = signal('');
    groupSearchQuery = signal('');

    // Computed filtered lists
    filteredCourses = computed(() => {
        const query = this.courseSearchQuery().toLowerCase();
        const all = this.courses();
        if (!query) return all;
        return all.filter(c => c.title.toLowerCase().includes(query));
    });

    filteredGroups = computed(() => {
        const query = this.groupSearchQuery().toLowerCase();
        const all = this.groups();
        if (!query) return all;
        return all.filter(g => g.name.toLowerCase().includes(query));
    });

    // Helper to get selected names
    selectedCourseName = computed(() => {
        const id = this.selectedCourseId();
        if (!id) return null;
        return this.courses().find(c => c.id == id)?.title || 'Unknown Course';
    });

    selectedGroupName = computed(() => {
        const id = this.selectedGroupId();
        if (!id) return null;
        return this.groups().find(g => g.id == id)?.name || 'Unknown Group';
    });

    isLoading = signal(false);

    ngOnInit() {
        this.loadGroups();
        this.loadCourses();
        this.loadResults();
    }

    @HostListener('document:click', ['$event'])
    onClick(event: MouseEvent) {
        if (!this.elementRef.nativeElement.contains(event.target)) {
            this.courseDropdownOpen.set(false);
            this.groupDropdownOpen.set(false);
        } else {
            // Handle clicking inside comp but outside specific dropdowns
            // This is tricky with simple approach, so we'll do:
            // logic is typically handled by simple stopPropagation on container click or check target
            // implementation inside template click handlers or checking closest
            const target = event.target as HTMLElement;
            if (!target.closest('.course-dropdown')) this.courseDropdownOpen.set(false);
            if (!target.closest('.group-dropdown')) this.groupDropdownOpen.set(false);
        }
    }

    loadGroups() {
        this.apiService.getGroups().subscribe({
            next: (groups) => this.groups.set(groups),
            error: () => { }
        });
    }

    loadCourses() {
        this.courseService.getCourses().subscribe({
            next: (courses) => this.courses.set(courses),
            error: () => { }
        });
    }

    loadResults() {
        this.isLoading.set(true);
        const params: any = {};

        if (this.selectedGroupId()) params.group_id = this.selectedGroupId();
        if (this.search()) params.search = this.search();

        // Switch mode based on Course Selection
        if (this.selectedCourseId()) {
            params.view = 'course_progress';
            params.course_id = this.selectedCourseId();
            if (this.statusFilter() !== 'all') {
                params.status = this.statusFilter();
            }
        } else if (this.selectedGroupId()) {
            params.view = 'group_status';
            if (this.statusFilter() !== 'all') {
                params.status = this.statusFilter();
            }
        } else {
            params.view = 'recent';
        }

        this.apiService.getResults(params).subscribe({
            next: (data) => {
                this.results.set(data);
                this.isLoading.set(false);
            },
            error: (err) => {
                console.error('Failed to load results', err);
                this.isLoading.set(false);
            }
        });
    }

    onFilterChange() {
        this.loadResults();
    }

    // Dropdown helpers
    selectCourse(courseId: string) {
        this.selectedCourseId.set(courseId);
        this.courseDropdownOpen.set(false);
        this.onFilterChange();
    }

    selectGroup(groupId: string) {
        this.selectedGroupId.set(groupId);
        this.groupDropdownOpen.set(false);
        this.onFilterChange();
    }

    exportCsv() {
        const params: any = {};
        if (this.selectedGroupId()) params.group_id = this.selectedGroupId();
        if (this.search()) params.search = this.search();

        if (this.selectedCourseId()) {
            params.view = 'course_progress';
            params.course_id = this.selectedCourseId();
            if (this.statusFilter() !== 'all') {
                params.status = this.statusFilter();
            }
        } else {
            params.view = 'recent';
        }

        this.apiService.exportResults(params).subscribe({
            next: (data) => {
                const blob = new Blob([data], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'results.csv';
                a.click();
                window.URL.revokeObjectURL(url);
            },
            error: (err) => alert('Export failed')
        });
    }
}
