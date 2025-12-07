import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';
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
    private authService = inject(AuthService);

    results = signal<any[]>([]);
    groups = signal<any[]>([]);

    // Filter state
    search = signal('');
    selectedGroupId = signal<string>('');

    isLoading = signal(false);

    ngOnInit() {
        this.loadGroups();
        this.loadResults();
    }

    loadGroups() {
        // If admin, load all groups. If monitor, we technically only see assigned groups.
        // However, the backend /groups endpoint returns all groups currently or maybe only visible?
        // Let's assume for now we list all groups for filtering if admin (or let the dropdown be populated by what is returned).
        // The previous implementation of getGroups() returns all. Monitors shouldn't see all groups in dropdown if they can't access them...
        // But let's keep it simple: Filter dropdown by groups they actually have monitor access to?
        // Getting "My Monitored Groups" would be a nice endpoint, but for now let's just use what we have.
        // If getting 403 on specific group, so be it.

        // Better: If we had an endpoint /my-monitored-groups.
        // For now, let's load all and handle errors or filtering in backend.
        this.apiService.getGroups().subscribe({
            next: (groups) => this.groups.set(groups),
            error: () => { } // Maybe 403 if not admin?
        });
    }

    loadResults() {
        this.isLoading.set(true);
        const params: any = {};
        if (this.selectedGroupId()) params.group_id = this.selectedGroupId();
        if (this.search()) params.search = this.search();

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

    exportCsv() {
        const params: any = {};
        if (this.selectedGroupId()) params.group_id = this.selectedGroupId();
        if (this.search()) params.search = this.search();

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
