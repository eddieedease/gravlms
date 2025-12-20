import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators, FormControl } from '@angular/forms';
import { TranslateModule } from '@ngx-translate/core';
import { ModalComponent } from '../modal/modal';
import { LtiManagementComponent } from './lti-management/lti-management';
import { UserService } from '../../services/user.service';
import { GroupsService } from '../../services/groups.service';
import { CourseService } from '../../services/course.service';
import { LearningService } from '../../services/learning.service';
import { ApiService } from '../../services/api.service';
import { OrganisationService } from '../../services/organisation.service';

@Component({
  selector: 'app-admin',
  imports: [CommonModule, ReactiveFormsModule, TranslateModule, ModalComponent, LtiManagementComponent],
  templateUrl: './admin.html',
  providers: [DatePipe]
})
export class Admin implements OnInit {
  private fb = inject(FormBuilder);
  private userService = inject(UserService);
  private groupsService = inject(GroupsService);
  private courseService = inject(CourseService);
  private learningService = inject(LearningService);
  private apiService = inject(ApiService);
  public organisationService = inject(OrganisationService);

  // Tab State
  activeTab = signal<'users' | 'groups' | 'lti' | 'email' | 'organisation'>('users');
  searchTerm = signal('');

  // Data Signals
  users = signal<any[]>([]);
  courses = signal<any[]>([]);
  groups = signal<any[]>([]);

  // Computed / Filtered
  filteredUsers = () => {
    const term = this.searchTerm().toLowerCase();
    return this.users().filter(u => u.username.toLowerCase().includes(term) || u.email.toLowerCase().includes(term));
  };

  filteredGroups = () => {
    const term = this.searchTerm().toLowerCase();
    return this.groups().filter(g => g.name.toLowerCase().includes(term));
  };

  // User Management
  showForm = signal(false);
  isEditing = signal(false);
  editingUserId: number | null = null;
  assignedCourses = signal<any[]>([]);

  form = this.fb.group({
    username: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: [''],
    role: ['user']
  });

  assignForm = this.fb.group({
    userId: ['', Validators.required],
    courseId: ['', Validators.required]
  });

  // Group Management
  showGroupForm = signal(false);
  isEditingGroup = signal(false);
  editingGroupId: number | null = null;
  selectedGroup: any | null = null;
  showGroupDetails = signal(false);

  groupForm = this.fb.group({
    name: ['', Validators.required],
    description: ['']
  });

  // Group Details Forms
  assignGroupUserForm = this.fb.group({
    userId: ['', Validators.required]
  });
  assignGroupMonitorForm = this.fb.group({
    userId: ['', Validators.required]
  });
  assignGroupCourseForm = this.fb.group({
    courseId: ['', Validators.required],
    validityDays: ['']
  });

  groupUsers = signal<any[]>([]);
  groupMonitors = signal<any[]>([]);
  groupCourses = signal<any[]>([]);

  // Email Settings
  testEmailControl = new FormControl('', [Validators.required, Validators.email]);
  isSendingEmail = signal(false);
  emailStatus = signal<{ success: boolean; message: string } | null>(null);

  // Organisation Settings
  orgForm = this.fb.group({
    org_name: ['', Validators.required],
    org_slogan: [''],
    org_main_color: ['#3b82f6'],
    org_email: ['', [Validators.email]],
    news_message_enabled: [false],
    news_message_content: ['']
  });

  orgLogoUrl = signal<string | null>(null);
  orgHeaderUrl = signal<string | null>(null);
  isSavingOrg = signal(false);

  ngOnInit() {
    this.loadUsers();
    this.loadCourses();
    this.loadGroups();

    // Org settings loaded via service, but we patch form
    this.patchOrgForm(this.organisationService.settings());

    // Watch assigned courses form changes to load user courses?
    this.assignForm.get('userId')?.valueChanges.subscribe(userId => {
      if (userId) {
        this.loadUserCourses(+userId);
      } else {
        this.assignedCourses.set([]);
      }
    });
  }

  setTab(tab: 'users' | 'groups' | 'lti' | 'email' | 'organisation') {
    this.activeTab.set(tab);
    this.searchTerm.set('');
    if (tab === 'organisation') {
      this.patchOrgForm(this.organisationService.settings());
    }
  }

  // --- User Logic ---
  loadUsers() {
    this.userService.getUsers().subscribe(users => this.users.set(users));
  }
  loadCourses() {
    this.courseService.getCourses().subscribe(courses => this.courses.set(courses));
  }

  startCreate() {
    this.isEditing.set(false);
    this.editingUserId = null;
    this.form.reset({ role: 'user' });
    this.showForm.set(true);
  }

  startEdit(user: any) {
    this.isEditing.set(true);
    this.editingUserId = user.id;
    this.form.patchValue({
      username: user.username,
      email: user.email,
      role: user.role,
      password: '' // Don't populate password
    });
    this.showForm.set(true);
  }

  deleteUser(id: number) {
    if (confirm('Are you sure you want to delete this user?')) {
      this.userService.deleteUser(id).subscribe(() => this.loadUsers());
    }
  }

  onSubmit() {
    if (this.form.valid) {
      if (this.isEditing() && this.editingUserId) {
        const data = this.form.value;
        if (!data.password) delete data.password;
        this.userService.updateUser(this.editingUserId, data).subscribe(() => {
          this.loadUsers();
          this.showForm.set(false);
        });
      } else {
        this.userService.createUser(this.form.value).subscribe(() => {
          this.loadUsers();
          this.showForm.set(false);
        });
      }
    }
  }
  cancel() {
    this.showForm.set(false);
  }

  // User Course Assignment
  loadUserCourses(userId: number) {
    this.learningService.getUserCourses(userId).subscribe(courses => {
      this.assignedCourses.set(courses);
    });
  }

  onAssign() {
    if (this.assignForm.valid) {
      const { userId, courseId } = this.assignForm.value;
      if (userId && courseId) {
        this.learningService.assignCourse(+userId, +courseId).subscribe(() => {
          this.loadUserCourses(+userId);
          alert('Course assigned successfully');
        });
      }
    }
  }

  detachCourse(courseId: number) {
    const userId = this.assignForm.get('userId')?.value;
    if (userId && confirm('Remove this course assignment?')) {
      this.learningService.detachCourse(+userId, courseId).subscribe(() => {
        this.loadUserCourses(+userId);
      });
    }
  }

  // --- Group Logic ---
  loadGroups() {
    this.groupsService.getGroups().subscribe(groups => this.groups.set(groups));
  }

  startCreateGroup() {
    this.isEditingGroup.set(false);
    this.editingGroupId = null;
    this.groupForm.reset();
    this.showGroupForm.set(true);
  }

  startEditGroup(group: any) {
    this.isEditingGroup.set(true);
    this.editingGroupId = group.id;
    this.groupForm.patchValue({ name: group.name, description: group.description });
    this.showGroupForm.set(true);
  }

  deleteGroup(id: number) {
    if (confirm('Delete this group?')) {
      this.groupsService.deleteGroup(id).subscribe(() => this.loadGroups());
    }
  }

  onSubmitGroup() {
    if (this.groupForm.valid) {
      if (this.isEditingGroup() && this.editingGroupId) {
        this.groupsService.updateGroup(this.editingGroupId, this.groupForm.value).subscribe(() => {
          this.loadGroups();
          this.showGroupForm.set(false);
        });
      } else {
        this.groupsService.createGroup(this.groupForm.value).subscribe(() => {
          this.loadGroups();
          this.showGroupForm.set(false);
        });
      }
    }
  }

  cancelGroup() {
    this.showGroupForm.set(false);
  }

  selectGroup(group: any) {
    this.selectedGroup = group;
    this.showGroupDetails.set(true);
    this.loadGroupDetails(group.id);
  }

  closeGroupDetails() {
    this.selectedGroup = null;
    this.showGroupDetails.set(false);
  }

  loadGroupDetails(groupId: number) {
    this.groupsService.getGroupUsers(groupId).subscribe(u => this.groupUsers.set(u));
    this.apiService.getGroupMonitors(groupId).subscribe(m => this.groupMonitors.set(m));
    this.groupsService.getGroupCourses(groupId).subscribe(c => this.groupCourses.set(c));
  }

  addUserToGroup() {
    if (this.assignGroupUserForm.valid && this.selectedGroup) {
      const userId = this.assignGroupUserForm.get('userId')?.value;
      if (userId) {
        this.groupsService.addUserToGroup(this.selectedGroup.id, +userId).subscribe(() => {
          this.loadGroupDetails(this.selectedGroup.id);
          this.assignGroupUserForm.reset();
        });
      }
    }
  }

  removeUserFromGroup(userId: number) {
    if (this.selectedGroup && confirm('Remove user from group?')) {
      this.groupsService.removeUserFromGroup(this.selectedGroup.id, userId).subscribe(() => {
        this.loadGroupDetails(this.selectedGroup.id);
      });
    }
  }

  // Monitor Logic
  addMonitorToGroup() {
    if (this.assignGroupMonitorForm.valid && this.selectedGroup) {
      const userId = this.assignGroupMonitorForm.get('userId')?.value;
      if (userId) {
        this.apiService.addMonitorToGroup(this.selectedGroup.id, +userId).subscribe(() => {
          this.loadGroupDetails(this.selectedGroup.id);
          this.assignGroupMonitorForm.reset();
        });
      }
    }
  }

  removeMonitorFromGroup(userId: number) {
    if (this.selectedGroup && confirm('Remove monitor from group?')) {
      this.apiService.removeMonitorFromGroup(this.selectedGroup.id, userId).subscribe(() => {
        this.loadGroupDetails(this.selectedGroup.id);
      });
    }
  }

  // Group Course Logic
  addCourseToGroup() {
    if (this.assignGroupCourseForm.valid && this.selectedGroup) {
      const { courseId, validityDays } = this.assignGroupCourseForm.value;
      if (courseId) {
        this.groupsService.addCourseToGroup(this.selectedGroup.id, +courseId, validityDays ? +validityDays : undefined).subscribe(() => {
          this.loadGroupDetails(this.selectedGroup.id);
          this.assignGroupCourseForm.reset();
        });
      }
    }
  }

  removeCourseFromGroup(courseId: number) {
    if (this.selectedGroup && confirm('Remove course from group?')) {
      this.groupsService.removeCourseFromGroup(this.selectedGroup.id, courseId).subscribe(() => {
        this.loadGroupDetails(this.selectedGroup.id);
      });
    }
  }


  // --- Email Logic ---
  sendTestEmail() {
    if (this.testEmailControl.valid) {
      this.isSendingEmail.set(true);
      this.emailStatus.set(null);
      this.apiService.sendTestEmail(this.testEmailControl.value!).subscribe({
        next: () => {
          this.isSendingEmail.set(false);
          this.emailStatus.set({ success: true, message: 'Email sent successfully!' });
        },
        error: (err) => {
          this.isSendingEmail.set(false);
          this.emailStatus.set({ success: false, message: 'Failed to send email: ' + (err.error?.error || 'Unknown error') });
        }
      });
    }
  }

  // --- Organisation Logic ---
  patchOrgForm(settings: any) {
    this.orgForm.patchValue({
      org_name: settings.org_name,
      org_slogan: settings.org_slogan,
      org_main_color: settings.org_main_color,
      org_email: settings.org_email,
      news_message_enabled: settings.news_message_enabled,
      news_message_content: settings.news_message_content
    });
    this.orgLogoUrl.set(settings.org_logo_url);
    this.orgHeaderUrl.set(settings.org_header_image_url);
  }

  onOrgSubmit() {
    if (this.orgForm.valid) {
      this.isSavingOrg.set(true);
      const data = {
        ...this.organisationService.settings(),
        ...this.orgForm.value,
        org_logo_url: this.orgLogoUrl(),
        org_header_image_url: this.orgHeaderUrl()
      };

      this.organisationService.updateSettings(data as any).subscribe({
        next: () => {
          this.isSavingOrg.set(false);
          alert('Organisation settings saved successfully');
        },
        error: (err) => {
          this.isSavingOrg.set(false);
          alert('Failed to save settings');
        }
      });
    }
  }

  onLogoSelected(event: any) {
    const file = event.target.files[0];
    if (file) {
      this.organisationService.uploadImage(file).subscribe({
        next: (res) => {
          this.orgLogoUrl.set(res.url);
        },
        error: () => alert('Failed to upload logo')
      });
    }
  }

  onHeaderImageSelected(event: any) {
    const file = event.target.files[0];
    if (file) {
      this.organisationService.uploadImage(file).subscribe({
        next: (res) => {
          this.orgHeaderUrl.set(res.url);
        },
        error: () => alert('Failed to upload header image')
      });
    }
  }

  removeLogo() {
    this.orgLogoUrl.set(null);
  }

  removeHeaderImage() {
    this.orgHeaderUrl.set(null);
  }
}
