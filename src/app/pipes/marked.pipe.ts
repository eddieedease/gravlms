import { Pipe, PipeTransform } from '@angular/core';
import { marked } from 'marked';

@Pipe({
    name: 'marked',
    standalone: true
})
export class MarkedPipe implements PipeTransform {
    transform(value: any): any {
        if (value && value.length > 0) {
            const renderer = new marked.Renderer();

            // Configure marked options if needed

            let html = marked.parse(value, { renderer }) as string;

            // Replace [lti-tool id="123"] with a button
            html = html.replace(/\[lti-tool id="(\d+)"\]/g, (match, id) => {
                return `<div class="lti-launch-container my-4 p-4 border rounded-lg bg-gray-50 text-center">
                <p class="mb-2 text-gray-600 font-medium">External Tool</p>
                <button class="lti-launch-btn btn-primary" data-tool-id="${id}">
                  Launch Tool
                </button>
              </div>`;
            });

            return html;
        }
        return value;
    }
}
