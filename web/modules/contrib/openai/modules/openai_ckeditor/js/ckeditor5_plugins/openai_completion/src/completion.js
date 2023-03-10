import Ui from './ui';
import { Plugin } from 'ckeditor5/src/core';

export default class Completion extends Plugin {
  static get requires() {
    return [Ui];
  }
}
