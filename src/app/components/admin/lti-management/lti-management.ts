import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiService } from '../../../services/api.service';
import { DatePipe } from '@angular/common';
import { ModalComponent } from '../../modal/modal';

@Component({
    selector: 'app-lti-management',
    imports: [ReactiveFormsModule, DatePipe, ModalComponent],
    templateUrl: './lti-management.html'
})
export class LtiManagementComponent implements OnInit {
    private apiService = inject(ApiService);
    private fb = inject(FormBuilder);

    platforms = signal<any[]>([]);
    tools = signal<any[]>([]);

    activeTab = signal<'platforms' | 'tools'>('platforms');
    showForm = signal(false);

    // Platform Form (LTI 1.3 Issuer)
    platformForm = this.fb.group({
        issuer: ['', Validators.required],
        client_id: ['', Validators.required],
        auth_login_url: ['', Validators.required],
        auth_token_url: ['', Validators.required],
        key_set_url: ['', Validators.required],
        deployment_id: ['']
    });

    // Tool Form (External Tool)
    toolForm = this.fb.group({
        name: ['', Validators.required],
        tool_url: ['', Validators.required],
        lti_version: ['1.3', Validators.required],
        client_id: [''],
        public_key: [''],
        initiate_login_url: [''],
        consumer_key: [''],
        shared_secret: ['']
    });

    ngOnInit() {
        this.loadPlatforms();
        this.loadTools();
    }

    loadPlatforms() {
        this.apiService.getLtiPlatforms().subscribe(platforms => {
            this.platforms.set(platforms);
        });
    }

    loadTools() {
        this.apiService.getLtiTools().subscribe(tools => {
            this.tools.set(tools);
        });
    }

    startCreate() {
        this.showForm.set(true);
        this.platformForm.reset();
        this.toolForm.reset({ lti_version: '1.3' });
    }

    cancel() {
        this.showForm.set(false);
    }

    onSubmitPlatform() {
        if (this.platformForm.valid) {
            this.apiService.createLtiPlatform(this.platformForm.value).subscribe(() => {
                this.loadPlatforms();
                this.cancel();
            });
        }
    }

    onSubmitTool() {
        if (this.toolForm.valid) {
            this.apiService.createLtiTool(this.toolForm.value).subscribe(() => {
                this.loadTools();
                this.cancel();
            });
        }
    }
}
