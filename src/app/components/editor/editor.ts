import { Component, inject, OnInit, signal, computed, ElementRef, viewChild, HostListener } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { CourseService } from '../../services/course.service';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { ConfigService } from '../../services/config.service';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { marked } from 'marked';
import { TestEditorComponent } from '../test-editor/test-editor';

import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-editor',
  imports: [ReactiveFormsModule, TestEditorComponent, TranslateModule],
  templateUrl: './editor.html',
  styleUrl: './editor.css',
})
export class Editor implements OnInit {
  private courseService = inject(CourseService);
  private authService = inject(AuthService);
  private http = inject(HttpClient);
  private apiService = inject(ApiService);
  private config = inject(ConfigService);
  private fb = inject(FormBuilder);
  private sanitizer = inject(DomSanitizer);

  fileInput = viewChild<ElementRef<HTMLInputElement>>('fileInput');
  courseThumbnailInput = viewChild<ElementRef<HTMLInputElement>>('courseThumbnailInput');
  markdownTextarea = viewChild<ElementRef<HTMLTextAreaElement>>('markdownTextarea');
  uploadStatus = signal<string>('');
  courseImageFile = signal<File | null>(null);
  courseImagePreview = signal<string>('');

  courses = signal<any[]>([]);
  searchQuery = signal<string>('');

  filteredCourses = computed(() => {
    const query = this.searchQuery().toLowerCase();
    const allCourses = this.courses();

    if (!query) return allCourses;

    return allCourses.filter(course =>
      course.title.toLowerCase().includes(query)
    );
  });

  selectedCourse = signal<any>(null);

  pages = signal<any[]>([]);
  // Filter pages by selected course
  coursePages = computed(() => {
    if (!this.selectedCourse()) return [];
    return this.pages()
      .filter(p => p.course_id === this.selectedCourse().id)
      .sort((a, b) => a.display_order - b.display_order);
  });

  selectedPage = signal<any>(null);
  previewHtml = signal<SafeHtml | string>('');
  viewMode = signal<'editor' | 'split' | 'preview'>('editor');
  sidebarOpen = signal<boolean>(true);
  createItemDropdownOpen = signal<boolean>(false);
  ltiInfoOpen = signal<boolean>(false);

  toggleSidebar() {
    this.sidebarOpen.update(v => !v);
  }

  toggleLtiInfo() {
    this.ltiInfoOpen.update(v => !v);
  }

  toggleCreateItemDropdown() {
    this.createItemDropdownOpen.update(v => !v);
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent) {
    const target = event.target as HTMLElement;
    const dropdown = target.closest('.create-item-dropdown');

    // Close dropdown if clicking outside of it
    if (!dropdown && this.createItemDropdownOpen()) {
      this.createItemDropdownOpen.set(false);
    }
  }

  ltiTools = signal<any[]>([]);

  pageForm = this.fb.group({
    title: ['', Validators.required],
    content: [''],
    type: ['page'],
    instructions: [''], // For assessment
    course_id: [null as number | null]
  });

  courseForm = this.fb.group({
    title: ['', Validators.required],
    description: [''],
    is_lti: [false],
    lti_tool_id: [null as number | null],
    custom_launch_url: [''],
    image_url: ['']
  });

  ngOnInit() {
    this.loadCourses();
    this.loadPages();
    this.loadLtiTools();

    // Update preview when content changes
    this.pageForm.get('content')?.valueChanges.subscribe(val => {
      this.updatePreview(val || '');
    });
  }

  async updatePreview(content: string) {
    const html = await marked.parse(content);
    this.previewHtml.set(this.sanitizer.bypassSecurityTrustHtml(html));
  }

  loadCourses() {
    this.courseService.getCourses().subscribe(courses => {
      this.courses.set(courses);
      // Restore selection if needed
      if (this.selectedCourse()) {
        const updated = courses.find(c => c.id === this.selectedCourse().id);
        this.selectedCourse.set(updated || null);
      }
    });
  }

  loadPages() {
    this.courseService.getPages().subscribe(pages => {
      this.pages.set(pages);
      if (this.selectedPage()) {
        const updated = pages.find(p => p.id === this.selectedPage().id);
        if (updated) {
          this.selectPage(updated);
        } else {
          this.selectedPage.set(null);
        }
      }
    });
  }

  selectCourse(course: any) {
    this.selectedCourse.set(course);
    this.selectedPage.set(null);

    // Patch course form
    this.courseForm.patchValue({
      title: course.title,
      description: course.description,
      is_lti: !!course.is_lti,
      lti_tool_id: course.lti_tool_id,
      custom_launch_url: course.custom_launch_url,
      image_url: course.image_url || ''
    });

    // Set preview if image exists
    if (course.image_url) {
      this.courseImagePreview.set(`${this.config.apiUrl}/uploads/${course.image_url}`);
    } else {
      this.courseImagePreview.set('');
    }
    this.courseImageFile.set(null);
  }

  createCourse() {
    const title = prompt('Enter course title:');
    if (title) {
      this.courseService.createCourse({ title }).subscribe(() => {
        this.loadCourses();
      });
    }
  }

  saveCourse() {
    if (this.selectedCourse() && this.courseForm.valid) {
      // If there's a new image file, upload it first
      if (this.courseImageFile()) {
        this.uploadCourseImage();
      } else {
        this.updateCourseData();
      }
    }
  }

  updateCourseData() {
    const updatedCourse = {
      ...this.selectedCourse(),
      ...this.courseForm.value
    };

    // Ensure types are correct for backend
    if (!updatedCourse.is_lti) {
      updatedCourse.lti_tool_id = null;
      updatedCourse.custom_launch_url = null;
    }

    this.courseService.updateCourse(this.selectedCourse().id, updatedCourse).subscribe(() => {
      this.loadCourses();
      alert('Course saved!');
    });
  }

  deleteCourse(course: any) {
    if (confirm(`Delete course "${course.title}" and all its pages?`)) {
      this.courseService.deleteCourse(course.id).subscribe(() => {
        this.selectedCourse.set(null);
        this.loadCourses();
        this.loadPages(); // Pages might be deleted by cascade or need refresh
      });
    }
  }

  selectPage(page: any) {
    this.selectedPage.set(page);
    // On mobile, auto-close sidebar when selecting a page
    if (window.innerWidth < 640) { // sm breakpoint
      this.sidebarOpen.set(false);
    }
    this.pageForm.patchValue({
      title: page.title,
      content: page.content,
      type: page.type || 'page',
      course_id: page.course_id,
      instructions: '' // Reset instructions, ideally fetch them if assessment
    });

    // If assessment, fetch details?
    // Actually our pages list currently doesn't include instructions.
    // We might need to fetch them.
    // However, the user request says: "When creating an assigment type page we can form an assignment and ask for an optional file upload."
    // If we want to EDIT it, we need to load it. 
    // Let's assume for now we use the `getAssessmentForPage` API if type is assessment.

    if (page.type === 'assessment') {
      this.apiService.getAssessmentForPage(page.id).subscribe({
        next: (res) => {
          if (res.assessment) {
            this.pageForm.patchValue({ instructions: res.assessment.instructions });
          }
        },
        error: () => { } // Ignore if new
      });
    }

    this.updatePreview(page.content || '');
  }

  createPage(type: 'page' | 'test' | 'video' | 'assessment' = 'page') {
    if (!this.selectedCourse()) return;
    const newPage = {
      title: type === 'test' ? 'New Test' : (type === 'video' ? 'New Video' : (type === 'assessment' ? 'New Assessment' : 'New Page')),
      content: '',
      type: type,
      course_id: this.selectedCourse().id,
      instructions: '' // For assessment
    };
    this.courseService.createPage(newPage).subscribe(() => {
      this.loadPages();
    });
  }

  savePage() {
    if (this.selectedPage() && this.pageForm.valid) {
      const updatedPage = { ...this.pageForm.value, course_id: this.selectedCourse().id };
      this.courseService.updatePage(this.selectedPage().id, updatedPage).subscribe(() => {
        this.loadPages();
        alert('Saved!');
      });
    }
  }

  deletePage() {
    if (this.selectedPage() && confirm('Are you sure?')) {
      this.courseService.deletePage(this.selectedPage().id).subscribe(() => {
        this.selectedPage.set(null);
        this.loadPages();
      });
    }
  }

  movePage(page: any, direction: 'up' | 'down') {
    const pages = this.coursePages();
    const index = pages.findIndex(p => p.id === page.id);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= pages.length) return;

    const otherPage = pages[newIndex];

    // Swap display_order
    // Ideally this should be done on backend or by swapping order values
    // For simplicity, let's assume display_order is index based or we just swap them
    // We need to update both pages

    // Assign temp orders
    const pageOrder = page.display_order || 0;
    const otherOrder = otherPage.display_order || 0;

    // If orders are same (e.g. 0), we need to fix them first. 
    // But let's just swap their current positions in the array and assign new orders based on index

    // Better approach: re-assign order for all pages in this course based on current array + swap
    const reordered = [...pages];
    [reordered[index], reordered[newIndex]] = [reordered[newIndex], reordered[index]];

    // Update all pages with new order
    reordered.forEach((p, i) => {
      if (p.display_order !== i) {
        this.courseService.updatePage(p.id, { ...p, display_order: i }).subscribe();
      }
    });

    // Optimistic update
    this.loadPages();
  }

  triggerFileInput() {
    this.fileInput()?.nativeElement.click();
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.uploadImage(input.files[0]);
    }
  }

  uploadImage(file: File) {
    // Validate file type
    if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
      this.uploadStatus.set('Error: Only JPG and PNG images are allowed');
      return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      this.uploadStatus.set('Error: File size must not exceed 5MB');
      return;
    }

    if (!this.selectedCourse()) {
      this.uploadStatus.set('Error: Please select a course first');
      return;
    }

    this.uploadStatus.set('Uploading...');

    const formData = new FormData();
    formData.append('image', file);
    formData.append('course_id', this.selectedCourse().id.toString());
    formData.append('type', 'content');

    const token = this.authService.getToken();
    const headers = new HttpHeaders({
      'Authorization': `Bearer ${token}`
    });

    this.http.post<any>(`${this.config.apiUrl}/uploads`, formData, { headers }).subscribe({
      next: (response) => {
        this.uploadStatus.set('Upload successful!');
        this.insertImageMarkdown(response.filename);
        setTimeout(() => this.uploadStatus.set(''), 3000);
      },
      error: (error) => {
        this.uploadStatus.set('Error: ' + (error.error?.error || 'Upload failed'));
        setTimeout(() => this.uploadStatus.set(''), 5000);
      }
    });
  }

  insertImageMarkdown(filename: string) {
    const imageUrl = `${this.config.apiUrl}/uploads/${filename}`;
    const markdown = `![Image](${imageUrl})`;

    const currentContent = this.pageForm.get('content')?.value || '';
    const newContent = currentContent + '\n\n' + markdown;

    this.pageForm.patchValue({ content: newContent });
    this.updatePreview(newContent);
  }

  loadLtiTools() {
    this.apiService.getLtiTools().subscribe(tools => {
      this.ltiTools.set(tools);
    });
  }

  triggerCourseThumbnailInput() {
    this.courseThumbnailInput()?.nativeElement.click();
  }

  onCourseThumbnailSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];

      // Validate file type
      if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
        alert('Only JPG and PNG images are allowed');
        return;
      }

      // Validate file size (5MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size must not exceed 5MB');
        return;
      }

      this.courseImageFile.set(file);

      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => {
        this.courseImagePreview.set(e.target?.result as string);
      };
      reader.readAsDataURL(file);
    }
  }

  uploadCourseImage() {
    const file = this.courseImageFile();
    if (!file || !this.selectedCourse()) return;

    this.courseService.uploadCourseImage(file, this.selectedCourse().id, 'thumbnail').subscribe({
      next: (response) => {
        // Update form with new image URL
        this.courseForm.patchValue({ image_url: response.filename });
        this.updateCourseData();
      },
      error: (error) => {
        alert('Image upload failed: ' + (error.error?.error || 'Unknown error'));
      }
    });
  }

  removeCourseThumbnail() {
    this.courseImageFile.set(null);
    this.courseImagePreview.set('');
    this.courseForm.patchValue({ image_url: null });
  }

  insertMarkdown(type: string) {
    const textarea = this.markdownTextarea()?.nativeElement;
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const beforeText = textarea.value.substring(0, start);
    const afterText = textarea.value.substring(end);

    let insertText = '';
    let cursorOffset = 0;

    switch (type) {
      case 'bold':
        insertText = `**${selectedText || 'bold text'}**`;
        cursorOffset = selectedText ? insertText.length : 2;
        break;
      case 'italic':
        insertText = `*${selectedText || 'italic text'}*`;
        cursorOffset = selectedText ? insertText.length : 1;
        break;
      case 'h1':
        insertText = `# ${selectedText || 'Heading 1'}`;
        cursorOffset = selectedText ? insertText.length : 2;
        break;
      case 'h2':
        insertText = `## ${selectedText || 'Heading 2'}`;
        cursorOffset = selectedText ? insertText.length : 3;
        break;
      case 'h3':
        insertText = `### ${selectedText || 'Heading 3'}`;
        cursorOffset = selectedText ? insertText.length : 4;
        break;
      case 'ul':
        insertText = selectedText
          ? selectedText.split('\n').map(line => `- ${line}`).join('\n')
          : '- List item';
        cursorOffset = selectedText ? insertText.length : 2;
        break;
      case 'ol':
        insertText = selectedText
          ? selectedText.split('\n').map((line, i) => `${i + 1}. ${line}`).join('\n')
          : '1. List item';
        cursorOffset = selectedText ? insertText.length : 3;
        break;
      case 'link':
        insertText = `[${selectedText || 'link text'}](url)`;
        cursorOffset = selectedText ? insertText.length - 4 : 1;
        break;
      case 'code':
        insertText = selectedText
          ? `\`\`\`\n${selectedText}\n\`\`\``
          : '```\ncode\n```';
        cursorOffset = selectedText ? insertText.length - 4 : 4;
        break;
      case 'quote':
        insertText = selectedText
          ? selectedText.split('\n').map(line => '> ' + line).join('\n')
          : '> Quote';
        cursorOffset = selectedText ? insertText.length : 2;
        break;
      case 'strike':
        insertText = `~~${selectedText || 'strikethrough'}~~`;
        cursorOffset = selectedText ? insertText.length : 2;
        break;
      case 'hr':
        insertText = '\n---\n';
        cursorOffset = 5;
        break;
      case 'table':
        insertText = `
| Header 1 | Header 2 |
| :--- | :--- |
| Row 1 | Data |
| Row 2 | Data |
`;
        cursorOffset = insertText.length;
        break;
      case 'info':
        insertText = `<div class="p-4 mb-4 bg-blue-50 text-blue-800 rounded-lg border border-blue-200">
  <strong>Info:</strong> ${selectedText || 'Your content here'}
</div>`;
        cursorOffset = insertText.length - 6; // before last </div>
        break;
      case 'warning':
        insertText = `<div class="p-4 mb-4 bg-orange-50 text-orange-800 rounded-lg border border-orange-200">
  <strong>Warning:</strong> ${selectedText || 'Your content here'}
</div>`;
        cursorOffset = insertText.length - 6;
        break;
      case 'danger':
        insertText = `<div class="p-4 mb-4 bg-red-50 text-red-800 rounded-lg border border-red-200">
  <strong>Danger:</strong> ${selectedText || 'Your content here'}
</div>`;
        cursorOffset = insertText.length - 6;
        break;
      case 'details':
        insertText = `<details class="group p-4 bg-gray-50 rounded-lg border border-gray-200">
  <summary class="font-bold cursor-pointer text-gray-800 select-none">
    ${selectedText || 'Click to view details'}
  </summary>
  <div class="mt-2 text-gray-700">
    Hidden content goes here...
  </div>
</details>`;
        cursorOffset = insertText.length - 19; // approximate position inside div
        break;
    }

    const newContent = beforeText + insertText + afterText;
    this.pageForm.patchValue({ content: newContent });
    this.updatePreview(newContent);

    // Set cursor position after update
    setTimeout(() => {
      textarea.focus();
      const newPosition = start + cursorOffset;
      textarea.setSelectionRange(newPosition, newPosition);
    }, 0);
  }
  ltiLaunchUrls = computed(() => {
    const course = this.selectedCourse();
    const tenantId = localStorage.getItem('tenantId');
    const apiBase = this.config.apiUrl.replace('/api', '');

    let lti13 = `${apiBase}/api/lti/launch`;
    let lti11 = `${apiBase}/api/lti11/launch`;

    if (course) {
      lti13 = `${lti13}/${course.id}`;
      lti11 = `${lti11}/${course.id}`;
    }

    if (tenantId) {
      lti13 += `?tenant=${tenantId}`;
      lti11 += `?tenant=${tenantId}`;
    }

    return { lti13, lti11 };
  });

  copyToClipboard(text: string) {
    navigator.clipboard.writeText(text).then(() => {
      this.uploadStatus.set('Copied to clipboard!');
      setTimeout(() => this.uploadStatus.set(''), 3000);
    });
  }
}
